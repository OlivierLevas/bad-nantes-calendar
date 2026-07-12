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
		wp_register_script(
			'bn-fullcalendar',
			BN_CALENDAR_URL . 'assets/vendor/fullcalendar/index.global.min.js',
			array(),
			'6.1.11',
			true
		);

		wp_register_script(
			'bn-init',
			BN_CALENDAR_URL . 'assets/js/bn-init.js',
			array( 'bn-fullcalendar' ),
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
		$initial_view = ( 'semaine' === strtolower( $atts['view'] ) ) ? 'timeGridWeek' : 'dayGridMonth';

		// id unique pour autoriser plusieurs calendriers sur une même page.
		self::$instance_count++;
		$container_id = 'bn-calendar-' . self::$instance_count;

		// Vue mobile choisie dans les réglages.
		$mobile_view = ( 'mois' === $options['mobile_default_view'] ) ? 'dayGridMonth' : 'listWeek';

		// Enqueue conditionnel : seulement quand le shortcode est effectivement rendu.
		wp_enqueue_style( 'bn-calendar' );
		wp_enqueue_script( 'bn-fullcalendar' );
		wp_enqueue_script( 'bn-init' );

		// Configuration transmise au JS (jamais de clé en dur côté JS).
		$config = array(
			'containerId'  => $container_id,
			'apiKey'       => $options['api_key'],
			'calendarId'   => $options['calendar_id'],
			'initialView'  => $initial_view,
			'mobileView'   => $mobile_view,
			'slotMinTime'  => $options['slot_min_time'] . ':00',
			'slotMaxTime'  => $options['slot_max_time'] . ':00',
			'firstDay'     => 1,
			'locale'       => 'fr',
			'mobileBreakpoint' => 600,
			'i18n'         => array(
				'missingConfig' => __( "Agenda non configuré : renseignez la clé API et l'ID d'agenda dans les réglages.", 'bad-nantes-calendar' ),
			),
		);

		// wp_localize_script crée une variable globale par instance de conteneur.
		wp_localize_script( 'bn-init', 'bnCalendarConfig_' . self::$instance_count, $config );

		// Conteneur ciblé par le JS.
		return sprintf(
			'<div class="bn-calendar-wrapper"><div id="%1$s" class="bn-calendar" data-instance="%2$d"></div></div>',
			esc_attr( $container_id ),
			(int) self::$instance_count
		);
	}
}
