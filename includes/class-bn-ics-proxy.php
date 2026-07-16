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
 * Endpoint admin-post (accessible connecté ou non) qui diffuse le flux ICS.
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
	 * Clé du transient de cache.
	 */
	const TRANSIENT_KEY = 'bn_calendar_ics_cache';

	/**
	 * Durée du cache en secondes (15 minutes).
	 */
	const CACHE_TTL = 900;

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
				'methods'  => 'GET',
				'callback' => array( $this, 'serve' ),
				// Flux public : pas de nonce, qui serait de toute façon périmé
				// dans les pages servies depuis un cache.
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Retourne l'URL publique du proxy à passer au front.
	 *
	 * @return string
	 */
	public static function get_proxy_url() {
		return rest_url( self::REST_NAMESPACE . self::REST_ROUTE );
	}

	/**
	 * Retourne les créneaux de la semaine en cours (lundi → dimanche).
	 *
	 * Source unique des créneaux côté serveur : le rendu HTML du shortcode et le
	 * schema.org en tirent tous deux leurs données, sur la même fenêtre.
	 *
	 * @return array Occurrences au format BN_Ics_Parser::occurrences(), éventuellement vide.
	 */
	public static function get_week_occurrences() {
		$ics = self::get_ics();

		if ( null === $ics ) {
			return array();
		}

		$timezone = wp_timezone();
		$from     = current_datetime()->setTimezone( $timezone )->modify( 'monday this week' )->setTime( 0, 0 );

		return BN_Ics_Parser::occurrences( $ics, $from, $from->modify( '+7 days' ), $timezone );
	}

	/**
	 * Récupère le flux ICS configuré, en s'appuyant sur le cache.
	 *
	 * Partagé par le proxy (qui le rediffuse au navigateur) et par le shortcode
	 * (qui en tire le rendu serveur des créneaux) : les deux passent par le même
	 * transient, donc une seule requête sortante toutes les CACHE_TTL secondes.
	 *
	 * @return string|null Contenu du flux, ou null si non configuré ou injoignable.
	 */
	public static function get_ics() {
		$options = BN_Settings::get_options();
		$ics_url = isset( $options['ics_url'] ) ? $options['ics_url'] : '';

		if ( empty( $ics_url ) ) {
			return null;
		}

		$body = get_transient( self::TRANSIENT_KEY );

		if ( false !== $body ) {
			return $body;
		}

		$response = wp_remote_get(
			$ics_url,
			array(
				'timeout'     => 15,
				'redirection' => 3,
				'user-agent'  => 'Bad-Nantes-Calendar/' . BN_CALENDAR_VERSION,
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$body = wp_remote_retrieve_body( $response );
		set_transient( self::TRANSIENT_KEY, $body, self::CACHE_TTL );

		return $body;
	}

	/**
	 * Récupère et diffuse le flux ICS (avec cache), puis termine la requête.
	 *
	 * Sert de callback aux deux points d'entrée (REST et admin_post). Le exit
	 * final court-circuite volontairement la sérialisation JSON de l'API REST :
	 * on diffuse du text/calendar brut, tel que le connecteur iCalendar l'attend.
	 */
	public function serve() {
		$body = self::get_ics();

		if ( null === $body ) {
			$options = BN_Settings::get_options();
			// Distingue « pas d'agenda configuré » (404) de « flux injoignable » (502).
			status_header( empty( $options['ics_url'] ) ? 404 : 502 );
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
