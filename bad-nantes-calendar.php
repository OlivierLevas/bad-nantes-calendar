<?php
/**
 * Plugin Name:       Bad'Nantes Calendar
 * Plugin URI:        https://github.com/OlivierLevas/bad-nantes-calendar
 * Description:        Affiche l'agenda Google public du club Bad'Nantes via FullCalendar 6, en vue semaine (grille horaire) ou vue mois. FullCalendar est embarqué en local, pas de CDN.
 * Version:           1.0.9
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Bad'Nantes
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bad-nantes-calendar
 * Domain Path:       /languages
 *
 * @package Bad_Nantes_Calendar
 */

// Sécurité : empêche l'accès direct au fichier.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Constantes du plugin.
 */
define( 'BN_CALENDAR_VERSION', '1.0.9' );
define( 'BN_CALENDAR_FILE', __FILE__ );
define( 'BN_CALENDAR_DIR', plugin_dir_path( __FILE__ ) );
define( 'BN_CALENDAR_URL', plugin_dir_url( __FILE__ ) );

/**
 * URL du dépôt GitHub utilisée par Plugin Update Checker pour les mises à jour.
 *
 */
define( 'BN_CALENDAR_GITHUB_URL', 'https://github.com/OlivierLevas/bad-nantes-calendar/' );

/**
 * Charge les classes du plugin.
 */
require_once BN_CALENDAR_DIR . 'includes/class-bn-settings.php';
require_once BN_CALENDAR_DIR . 'includes/class-bn-ics-proxy.php';
require_once BN_CALENDAR_DIR . 'includes/class-bn-shortcode.php';

/**
 * Charge le text domain pour l'internationalisation.
 */
function bn_calendar_load_textdomain() {
	load_plugin_textdomain(
		'bad-nantes-calendar',
		false,
		dirname( plugin_basename( BN_CALENDAR_FILE ) ) . '/languages'
	);
}
add_action( 'plugins_loaded', 'bn_calendar_load_textdomain' );

/**
 * Initialise les composants du plugin.
 */
function bn_calendar_init() {
	new BN_Settings();
	new BN_Ics_Proxy();
	new BN_Shortcode();
}
add_action( 'init', 'bn_calendar_init' );

/**
 * Hook d'activation : pose les options par défaut si elles n'existent pas encore.
 */
function bn_calendar_activate() {
	$defaults = array(
		'ics_url'            => '',
		'mobile_default_view' => 'liste',
	);

	$existing = get_option( 'bn_calendar_options', array() );
	update_option( 'bn_calendar_options', wp_parse_args( $existing, $defaults ) );
}
register_activation_hook( __FILE__, 'bn_calendar_activate' );

/**
 * Intègre Plugin Update Checker (Yahnis Elsts, v5) pour les mises à jour depuis GitHub.
 */
function bn_calendar_setup_update_checker() {
	$puc_loader = BN_CALENDAR_DIR . 'lib/plugin-update-checker/plugin-update-checker.php';
	if ( ! file_exists( $puc_loader ) ) {
		return;
	}

	require_once $puc_loader;

	// La factory v5 est exposée via l'alias YahnisElsts\PluginUpdateChecker\v5\PucFactory.
	if ( ! class_exists( 'YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory' ) ) {
		return;
	}

	$update_checker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		BN_CALENDAR_GITHUB_URL,
		BN_CALENDAR_FILE,
		'bad-nantes-calendar'
	);

	// Utilise les "release assets" (le .zip attaché à la release GitHub).
	$api = $update_checker->getVcsApi();
	if ( $api ) {
		$api->enableReleaseAssets();
	}
}
add_action( 'init', 'bn_calendar_setup_update_checker' );
