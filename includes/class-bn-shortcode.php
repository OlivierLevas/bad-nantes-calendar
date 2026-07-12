<?php
/**
 * Shortcode d'affichage du calendrier Bad'Nantes.
 *
 * @package Bad_Nantes_Calendar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enregistre le shortcode [bn_calendar] et enfile les assets de façon conditionnelle.
 */
class BN_Shortcode {

	/**
	 * Compteur pour générer des id de conteneur uniques.
	 *
	 * @var int
	 */
	private static $instance_count = 0;

	/**
	 * Enregistre le shortcode et les assets.
	 */
	public function __construct() {
		add_shortcode( 'bn_calendar', array( $this, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	/**
	 * Déclare (sans enfiler) les assets. L'enqueue réel se fait dans render().
	 */
	public function register_assets() {
		// Coeur FullCalendar (bundle standard, sans connecteurs).
		wp_register_script(
			'bn-fullcalendar',
			BN_CALENDAR_URL . 'assets/vendor/fullcalendar/index.global.min.js',
			array(),
			'6.1.11',
			true
		);

		// Locale française (enregistrée dans FullCalendar.globalLocales).
		wp_register_script(
			'bn-fc-locale-fr',
			BN_CALENDAR_URL . 'assets/vendor/fullcalendar/locales-fr.global.min.js',
			array( 'bn-fullcalendar' ),
			'6.1.11',
			true
		);

		// ical.js : parseur iCal (fournit le global ICAL, gère RRULE/fuseaux).
		wp_register_script(
			'bn-ical',
			BN_CALENDAR_URL . 'assets/vendor/ical/ical.min.js',
			array(),
			'1.5.0',
			true
		);

		// Connecteur iCalendar de FullCalendar (dépend du coeur + ICAL).
		wp_register_script(
			'bn-fc-icalendar',
			BN_CALENDAR_URL . 'assets/vendor/fullcalendar/icalendar.global.min.js',
			array( 'bn-fullcalendar', 'bn-ical' ),
			'6.1.11',
			true
		);

		wp_register_script(
			'bn-init',
			BN_CALENDAR_URL . 'assets/js/bn-init.js',
			array( 'bn-fc-icalendar', 'bn-fc-locale-fr' ),
			BN_CALENDAR_VERSION,
			true
		);

		wp_register_style(
			'bn-calendar',
			BN_CALENDAR_URL . 'assets/css/bn-calendar.css',
			array(),
			BN_CALENDAR_VERSION
		);
	}

	/**
	 * Rend le shortcode et enfile les assets uniquement à cet endroit.
	 *
	 * @param array $atts Attributs du shortcode.
	 * @return string
	 */
	public function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'view' => 'mois',
			),
			$atts,
			'bn_calendar'
		);

		$options = BN_Settings::get_options();

		// Traduit la vue FR en vue FullCalendar.
		// « semaine » = dayGridWeek : 7 colonnes (une par jour), événements listés
		// dans chaque colonne (pas de grille horaire).
		$initial_view = ( 'semaine' === strtolower( $atts['view'] ) ) ? 'dayGridWeek' : 'dayGridMonth';

		// id unique pour autoriser plusieurs calendriers sur une même page.
		self::$instance_count++;
		$container_id = 'bn-calendar-' . self::$instance_count;

		// Vue mobile choisie dans les réglages.
		$mobile_view = ( 'mois' === $options['mobile_default_view'] ) ? 'dayGridMonth' : 'listWeek';

		// Enqueue conditionnel : seulement quand le shortcode est effectivement rendu.
		// bn-init tire ses dépendances (coeur, ical, connecteur, locale) via wp_register_script.
		wp_enqueue_style( 'bn-calendar' );
		wp_enqueue_script( 'bn-init' );

		// Table lieu -> HTML : le HTML est produit ici à partir du gabarit commun, en
		// substituant les variables du lieu (échappées), puis assaini par wp_kses_post.
		// On abaisse la casse du repère pour un test insensible côté JS.
		$template  = isset( $options['location_template'] ) ? $options['location_template'] : '';
		$locations = array();
		foreach ( (array) $options['locations'] as $loc ) {
			$match   = isset( $loc['match'] ) ? $loc['match'] : '';
			$nom     = isset( $loc['nom'] ) ? $loc['nom'] : '';
			$adresse = isset( $loc['adresse'] ) ? $loc['adresse'] : '';
			$lien    = isset( $loc['lien'] ) ? $loc['lien'] : '';

			// Sans mot-clé de repérage, ou sans aucune variable renseignée (ex. lieux
			// hérités de la v1.0.4 : match+html mais ni nom/adresse/lien), on n'affiche
			// rien plutôt qu'un bloc dégénéré avec un lien Maps mort.
			if ( '' === $match || ( '' === $nom && '' === $adresse && '' === $lien ) ) {
				continue;
			}

			$html = '';
			if ( '' !== $template ) {
				$html = strtr(
					$template,
					array(
						'{{nom}}'     => esc_html( $nom ),
						'{{adresse}}' => esc_html( $adresse ),
						'{{lien}}'    => esc_url( $lien ),
					)
				);
				// Lien Maps non renseigné : on retire le lien mort (href vide) laissé par
				// le gabarit avant l'assainissement.
				if ( '' === $lien ) {
					$html = preg_replace( '#<a\b[^>]*\bhref=(?:""|\'\')[^>]*>.*?</a>#is', '', $html );
				}
				// Assainissement final, après substitution : le href contient alors une vraie URL.
				$html = wp_kses_post( $html );
			}

			$locations[] = array(
				'match' => function_exists( 'mb_strtolower' ) ? mb_strtolower( $match ) : strtolower( $match ),
				'html'  => $html,
			);
		}

		// Configuration transmise au JS (aucune donnée sensible : le flux est public).
		$config = array(
			'containerId'  => $container_id,
			'icsUrl'       => BN_Ics_Proxy::get_proxy_url(),
			'configured'   => ! empty( $options['ics_url'] ),
			'initialView'  => $initial_view,
			'mobileView'   => $mobile_view,
			'firstDay'     => 1,
			'locale'       => 'fr',
			'mobileBreakpoint' => 600,
			'locationHtml' => $locations,
			'i18n'         => array(
				'missingConfig' => __( "Agenda non configuré : renseignez l'URL du flux ICS dans les réglages.", 'bad-nantes-calendar' ),
			),
		);

		// La config voyage dans un attribut data- de la balise : robuste sur tous les
		// thèmes (y compris FSE/page builders), sans dépendre de l'ordre de chargement
		// des scripts comme le faisait wp_localize_script.
		$config_json = wp_json_encode( $config );

		// Conteneur ciblé par le JS.
		return sprintf(
			'<div class="bn-calendar-wrapper"><div id="%1$s" class="bn-calendar" data-config="%2$s"></div></div>',
			esc_attr( $container_id ),
			esc_attr( $config_json )
		);
	}
}
