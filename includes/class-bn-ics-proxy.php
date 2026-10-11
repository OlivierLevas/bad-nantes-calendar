<?php
/**
 * Proxy ICS côté serveur pour Bad'Nantes Calendar.
 *
 * Récupère le flux iCal configuré (côté serveur, avec cache) et le ressert
 * en same-origin. Évite les problèmes de CORS des flux publics (Google, etc.)
 * et n'expose aucune clé.
 *
 * @package Bad_Nantes_Calendar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Endpoint REST public (bad-nantes/v1/ics) qui diffuse le flux ICS, doublé d'un
 * alias admin-post historique. Accessible connecté ou non.
 */
class BN_Ics_Proxy {

	/**
	 * Action admin-post utilisée par le proxy (alias historique, cf. __construct).
	 */
	const ACTION = 'bn_calendar_ics';

	/**
	 * Namespace REST du proxy.
	 */
	const REST_NAMESPACE = 'bad-nantes/v1';

	/**
	 * Route REST du proxy.
	 */
	const REST_ROUTE = '/ics';

	/**
	 * Préfixe des clés de transient : un cache par agenda, suffixé par son slug.
	 */
	const TRANSIENT_PREFIX = 'bn_calendar_ics_';

	/**
	 * Clé du transient mono-agenda des versions <= 1.0.21, purgée par purge_cache().
	 */
	const LEGACY_TRANSIENT_KEY = 'bn_calendar_ics_cache';

	/**
	 * Préfixe des options gardant la dernière copie valide de chaque flux,
	 * suffixé par le slug de l'agenda. Sans expiration : elle sert de repli tant
	 * que le flux distant ne répond pas.
	 */
	const BACKUP_PREFIX = 'bn_calendar_ics_last_';

	/**
	 * Durée du cache en secondes (15 minutes).
	 */
	const CACHE_TTL = 900;

	/**
	 * Durée pendant laquelle un flux en panne est mémorisé comme tel (2 minutes).
	 *
	 * Sans elle, chaque affichage de page relance un wp_remote_get de 15 s par
	 * agenda injoignable — et le schema.org fait le sien sur toutes les pages du
	 * site, pas seulement celles qui portent le shortcode.
	 */
	const FAILURE_TTL = 120;

	/**
	 * Enregistre l'endpoint pour les visiteurs connectés et anonymes.
	 *
	 * Le point d'entrée officiel est la route REST : le chemin /wp-admin/ des
	 * hooks admin_post est couramment bloqué par les filtrages réseau (VPN et
	 * proxies d'entreprise), ce qui rendait le flux inaccessible au navigateur
	 * alors même que le serveur le servait correctement.
	 *
	 * Les hooks admin_post restent branchés en alias : les pages HTML déjà en
	 * cache embarquent l'ancienne URL dans leur data-config et continuent de
	 * l'appeler jusqu'à leur purge.
	 */
	public function __construct() {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'serve' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'serve' ) );
		add_action( 'rest_api_init', array( $this, 'register_route' ) );
	}

	/**
	 * Déclare la route REST publique servant le flux.
	 */
	public function register_route() {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'serve' ),
				// Flux public : pas de nonce, qui serait de toute façon périmé
				// dans les pages servies depuis un cache.
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Retourne l'URL publique du proxy à passer au front.
	 *
	 * @param string $slug Identifiant de l'agenda ; vide pour l'URL sans paramètre.
	 * @return string
	 */
	public static function get_proxy_url( $slug = '' ) {
		$url = rest_url( self::REST_NAMESPACE . self::REST_ROUTE );

		// Un agenda = une URL distincte : le connecteur iCalendar de FullCalendar
		// mémorise son cache par URL, deux sources partageant la même ne seraient
		// pas récupérées séparément.
		if ( '' !== $slug ) {
			$url = add_query_arg( 'cal', $slug, $url );
		}

		return $url;
	}

	/**
	 * Retourne les agendas configurés, débarrassés des lignes inexploitables.
	 *
	 * @return array Liste de array( 'slug' => …, 'label' => …, 'url' => …, 'color' => … ).
	 */
	public static function get_calendars() {
		$options   = BN_Settings::get_options();
		$calendars = isset( $options['calendars'] ) ? (array) $options['calendars'] : array();
		$valid     = array();

		foreach ( $calendars as $calendar ) {
			if ( empty( $calendar['url'] ) || empty( $calendar['slug'] ) ) {
				continue;
			}
			$valid[] = $calendar;
		}

		return $valid;
	}

	/**
	 * Vide les caches de flux : ceux des agendas fournis, et l'ancienne clé unique.
	 *
	 * La dernière copie valide part avec : après un changement de réglages, elle
	 * peut venir d'une autre URL que celle désormais enregistrée pour ce slug.
	 *
	 * @param array|null $calendars Agendas à purger ; par défaut ceux enregistrés.
	 */
	public static function purge_cache( $calendars = null ) {
		if ( null === $calendars ) {
			$calendars = self::get_calendars();
		}

		foreach ( (array) $calendars as $calendar ) {
			if ( ! empty( $calendar['slug'] ) ) {
				delete_transient( self::TRANSIENT_PREFIX . $calendar['slug'] );
				delete_option( self::BACKUP_PREFIX . $calendar['slug'] );
			}
		}

		delete_transient( self::LEGACY_TRANSIENT_KEY );
	}

	/**
	 * Retourne les créneaux de la semaine en cours (lundi → dimanche).
	 *
	 * Source unique des créneaux côté serveur : le rendu HTML du shortcode et le
	 * schema.org en tirent tous deux leurs données, sur la même fenêtre.
	 *
	 * @return array Occurrences au format BN_Ics_Parser::occurrences(), plus la clé
	 *               'calendar' (nom de l'agenda d'origine). Éventuellement vide.
	 */
	public static function get_week_occurrences() {
		$timezone    = wp_timezone();
		$from        = current_datetime()->setTimezone( $timezone )->modify( 'monday this week' )->setTime( 0, 0 );
		$to          = $from->modify( '+7 days' );
		$occurrences = array();

		foreach ( self::get_calendars() as $calendar ) {
			$ics = self::get_ics( $calendar['slug'] );

			if ( null === $ics ) {
				continue;
			}

			foreach ( BN_Ics_Parser::occurrences( $ics, $from, $to, $timezone ) as $occurrence ) {
				// Le flux ICS ne dit pas de quel agenda il vient : le nom est ajouté ici.
				$occurrence['calendar'] = $calendar['label'];
				$occurrences[]          = $occurrence;
			}
		}

		// Chaque flux est trié, leur concaténation ne l'est pas.
		usort(
			$occurrences,
			static function ( $a, $b ) {
				return $a['start'] <=> $b['start'];
			}
		);

		return $occurrences;
	}

	/**
	 * Récupère le flux ICS configuré, en s'appuyant sur le cache.
	 *
	 * Partagé par le proxy (qui le rediffuse au navigateur) et par le shortcode
	 * (qui en tire le rendu serveur des créneaux) : les deux passent par le même
	 * transient, donc une seule requête sortante toutes les CACHE_TTL secondes.
	 *
	 * Quand le flux distant ne répond pas, la dernière copie valide est servie à
	 * sa place : les créneaux d'un club changent peu, une copie ancienne vaut
	 * mieux qu'un calendrier vide.
	 *
	 * @param string $slug Identifiant de l'agenda tel qu'enregistré dans les réglages.
	 * @return string|null Contenu du flux, ou null si l'agenda est inconnu, ou
	 *                     injoignable sans qu'aucune copie valide n'ait été gardée.
	 */
	public static function get_ics( $slug ) {
		$ics_url = '';

		// L'URL n'est jamais prise dans la requête : on ne retient que celle
		// enregistrée pour ce slug (sinon le proxy deviendrait un relais ouvert).
		foreach ( self::get_calendars() as $calendar ) {
			if ( $slug === $calendar['slug'] ) {
				$ics_url = $calendar['url'];
				break;
			}
		}

		if ( '' === $ics_url ) {
			return null;
		}

		$cached = get_transient( self::TRANSIENT_PREFIX . $slug );

		// La chaîne vide est le marqueur d'un flux qu'on vient de trouver en panne :
		// on ne le rappelle pas avant l'expiration du marqueur.
		if ( is_string( $cached ) ) {
			return '' !== $cached ? $cached : self::get_last_valid( $slug );
		}

		$response = wp_remote_get(
			$ics_url,
			array(
				'timeout'     => 15,
				'redirection' => 3,
				'user-agent'  => 'Bad-Nantes-Calendar/' . BN_CALENDAR_VERSION,
			)
		);

		$body = is_wp_error( $response ) ? '' : wp_remote_retrieve_body( $response );

		// Un 200 ne suffit pas : un portail captif ou un proxy sortant répond
		// volontiers une page HTML d'erreur, qu'on servirait alors 15 minutes durant
		// comme un flux valide — sans jamais émettre le 502 qui l'aurait signalée.
		$valid = ! is_wp_error( $response )
			&& 200 === (int) wp_remote_retrieve_response_code( $response )
			&& false !== strpos( $body, 'BEGIN:VCALENDAR' );

		if ( ! $valid ) {
			set_transient( self::TRANSIENT_PREFIX . $slug, '', self::FAILURE_TTL );

			return self::get_last_valid( $slug );
		}

		set_transient( self::TRANSIENT_PREFIX . $slug, $body, self::CACHE_TTL );
		// Sans autoload : la copie n'est lue qu'en cas de panne.
		update_option( self::BACKUP_PREFIX . $slug, $body, false );

		return $body;
	}

	/**
	 * Retourne la dernière copie valide gardée pour un agenda.
	 *
	 * @param string $slug Identifiant de l'agenda.
	 * @return string|null Contenu du flux, ou null si aucune copie n'a été gardée.
	 */
	private static function get_last_valid( $slug ) {
		$backup = get_option( self::BACKUP_PREFIX . $slug, '' );

		return is_string( $backup ) && '' !== $backup ? $backup : null;
	}

	/**
	 * Récupère et diffuse le flux ICS (avec cache), puis termine la requête.
	 *
	 * Sert de callback aux deux points d'entrée (REST et admin_post). Le exit
	 * final court-circuite volontairement la sérialisation JSON de l'API REST :
	 * on diffuse du text/calendar brut, tel que le connecteur iCalendar l'attend.
	 */
	public function serve() {
		$calendars = self::get_calendars();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- flux public en lecture, servi aux visiteurs anonymes.
		$slug = isset( $_GET['cal'] ) ? sanitize_key( wp_unslash( $_GET['cal'] ) ) : '';

		// Paramètre absent : on sert le premier agenda. Les pages déjà en cache
		// appellent l'URL sans « cal » et ne doivent pas tomber en 404.
		if ( '' === $slug && array() !== $calendars ) {
			$slug = $calendars[0]['slug'];
		}

		$body = '' !== $slug ? self::get_ics( $slug ) : null;

		if ( null === $body ) {
			// Distingue « agenda inconnu ou non configuré » (404) de « flux injoignable » (502).
			$known = false;
			foreach ( $calendars as $calendar ) {
				if ( $slug === $calendar['slug'] ) {
					$known = true;
					break;
				}
			}

			status_header( $known ? 502 : 404 );
			nocache_headers();
			exit;
		}

		// Diffusion du flux ICS brut.
		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Cache-Control: public, max-age=' . self::CACHE_TTL );
		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput -- flux iCal brut, pas du HTML.
		exit;
	}
}
