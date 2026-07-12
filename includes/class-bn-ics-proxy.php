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
	 * Action admin-post utilisée par le proxy.
	 */
	const ACTION = 'bn_calendar_ics';

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
	 */
	public function __construct() {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'serve' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'serve' ) );
	}

	/**
	 * Retourne l'URL publique du proxy à passer au front.
	 *
	 * @return string
	 */
	public static function get_proxy_url() {
		return add_query_arg( 'action', self::ACTION, admin_url( 'admin-post.php' ) );
	}

	/**
	 * Récupère et diffuse le flux ICS (avec cache), puis termine la requête.
	 */
	public function serve() {
		$options = BN_Settings::get_options();
		$ics_url = isset( $options['ics_url'] ) ? $options['ics_url'] : '';

		if ( empty( $ics_url ) ) {
			status_header( 404 );
			nocache_headers();
			exit;
		}

		$body = get_transient( self::TRANSIENT_KEY );

		if ( false === $body ) {
			$response = wp_remote_get(
				$ics_url,
				array(
					'timeout'     => 15,
					'redirection' => 3,
					'user-agent'  => 'Bad-Nantes-Calendar/' . BN_CALENDAR_VERSION,
				)
			);

			if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
				status_header( 502 );
				nocache_headers();
				exit;
			}

			$body = wp_remote_retrieve_body( $response );
			set_transient( self::TRANSIENT_KEY, $body, self::CACHE_TTL );
		}

		// Diffusion du flux ICS brut.
		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Cache-Control: public, max-age=' . self::CACHE_TTL );
		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput -- flux iCal brut, pas du HTML.
		exit;
	}
}
