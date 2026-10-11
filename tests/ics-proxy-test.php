<?php
/**
 * Tests du repli de BN_Ics_Proxy::get_ics() quand le flux distant ne répond pas.
 *
 * Script autonome, sans WordPress ni PHPUnit : php tests/ics-proxy-test.php
 *
 * @package Bad_Nantes_Calendar
 */

define( 'ABSPATH', __DIR__ );
define( 'BN_CALENDAR_VERSION', 'test' );

const BN_TEST_ICS = "BEGIN:VCALENDAR\nEND:VCALENDAR";

// État simulé de WordPress.
$bn_transients = array();
$bn_options    = array();
// Réponse du prochain wp_remote_get : array( code, corps ), ou null pour une WP_Error.
$bn_remote       = null;
$bn_remote_calls = 0;

class BN_Settings {
	public static function get_options() {
		return array(
			'calendars' => array(
				array(
					'slug'  => 'loisirs',
					'label' => 'Loisirs',
					'url'   => 'https://example.test/loisirs.ics',
				),
			),
		);
	}
}

function add_action() {}

function get_transient( $key ) {
	global $bn_transients;
	return array_key_exists( $key, $bn_transients ) ? $bn_transients[ $key ] : false;
}

function set_transient( $key, $value ) {
	global $bn_transients;
	$bn_transients[ $key ] = $value;
}

function delete_transient( $key ) {
	global $bn_transients;
	unset( $bn_transients[ $key ] );
}

function get_option( $key, $default = false ) {
	global $bn_options;
	return array_key_exists( $key, $bn_options ) ? $bn_options[ $key ] : $default;
}

function update_option( $key, $value ) {
	global $bn_options;
	$bn_options[ $key ] = $value;
}

function delete_option( $key ) {
	global $bn_options;
	unset( $bn_options[ $key ] );
}

function wp_remote_get() {
	global $bn_remote, $bn_remote_calls;
	$bn_remote_calls++;
	return $bn_remote;
}

function is_wp_error( $thing ) {
	return null === $thing;
}

function wp_remote_retrieve_body( $response ) {
	return $response[1];
}

function wp_remote_retrieve_response_code( $response ) {
	return $response[0];
}

require dirname( __DIR__ ) . '/includes/class-bn-ics-proxy.php';

$bn_failures = 0;

function bn_assert_same( $expected, $actual, $label ) {
	global $bn_failures;
	if ( $expected === $actual ) {
		echo "ok   - $label\n";
		return;
	}
	$bn_failures++;
	echo "FAIL - $label\n       attendu : " . var_export( $expected, true ) . "\n       obtenu  : " . var_export( $actual, true ) . "\n";
}

/**
 * Simule l'expiration du cache : seule la dernière copie valide survit.
 */
function bn_expire_cache() {
	global $bn_transients;
	$bn_transients = array();
}

// Flux en panne dès le premier appel : rien à servir.
$bn_remote = null;
bn_assert_same( null, BN_Ics_Proxy::get_ics( 'loisirs' ), 'panne sans copie : null' );

// Flux valide : servi et mémorisé.
bn_expire_cache();
$bn_remote = array( 200, BN_TEST_ICS );
bn_assert_same( BN_TEST_ICS, BN_Ics_Proxy::get_ics( 'loisirs' ), 'flux valide servi' );

// Panne réseau après expiration du cache : la dernière copie valide est servie.
bn_expire_cache();
$bn_remote = null;
bn_assert_same( BN_TEST_ICS, BN_Ics_Proxy::get_ics( 'loisirs' ), 'panne réseau : dernière copie valide' );

// Tant que la panne est mémorisée, la copie est servie sans rappeler le flux.
$bn_remote_calls = 0;
bn_assert_same( BN_TEST_ICS, BN_Ics_Proxy::get_ics( 'loisirs' ), 'panne mémorisée : dernière copie valide' );
bn_assert_same( 0, $bn_remote_calls, 'panne mémorisée : pas de nouvel appel sortant' );

// Page d'erreur HTML en 200 (portail captif) : traitée comme une panne.
bn_expire_cache();
$bn_remote = array( 200, '<html>Erreur</html>' );
bn_assert_same( BN_TEST_ICS, BN_Ics_Proxy::get_ics( 'loisirs' ), 'réponse illisible : dernière copie valide' );

// Erreur HTTP : idem.
bn_expire_cache();
$bn_remote = array( 503, '' );
bn_assert_same( BN_TEST_ICS, BN_Ics_Proxy::get_ics( 'loisirs' ), 'erreur 503 : dernière copie valide' );

// La purge (changement de réglages) efface aussi la copie : elle peut venir d'une autre URL.
BN_Ics_Proxy::purge_cache();
$bn_remote = null;
bn_assert_same( null, BN_Ics_Proxy::get_ics( 'loisirs' ), 'après purge : plus de copie' );

// Agenda inconnu : jamais de flux.
bn_assert_same( null, BN_Ics_Proxy::get_ics( 'inconnu' ), 'agenda inconnu : null' );

echo $bn_failures ? "\n$bn_failures échec(s)\n" : "\nTous les tests passent.\n";
exit( $bn_failures ? 1 : 0 );
