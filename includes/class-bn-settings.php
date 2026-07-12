<?php
/**
 * Page de réglages admin du plugin Bad'Nantes Calendar.
 *
 * @package Bad_Nantes_Calendar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gère la page « Réglages → Bad'Nantes Calendar » via la Settings API.
 */
class BN_Settings {

	/**
	 * Nom de l'option unique qui stocke tous les réglages.
	 */
	const OPTION_NAME = 'bn_calendar_options';

	/**
	 * Enregistre les hooks admin.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Retourne les réglages avec valeurs par défaut.
	 *
	 * @return array
	 */
	public static function get_options() {
		$defaults = array(
			'ics_url'             => '',
			'mobile_default_view' => 'liste',
		);

		return wp_parse_args( get_option( self::OPTION_NAME, array() ), $defaults );
	}

	/**
	 * Ajoute la page sous le menu Réglages.
	 */
	public function add_settings_page() {
		add_options_page(
			__( "Bad'Nantes Calendar", 'bad-nantes-calendar' ),
			__( "Bad'Nantes Calendar", 'bad-nantes-calendar' ),
			'manage_options',
			'bn-calendar',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Déclare le groupe d'options, la section et les champs.
	 */
	public function register_settings() {
		register_setting(
			'bn_calendar_settings_group',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_options' ),
				'default'           => array(),
			)
		);

		add_settings_section(
			'bn_calendar_main_section',
			__( "Configuration du flux d'agenda (ICS)", 'bad-nantes-calendar' ),
			array( $this, 'render_section_intro' ),
			'bn-calendar'
		);

		add_settings_field(
			'ics_url',
			__( "URL du flux ICS", 'bad-nantes-calendar' ),
			array( $this, 'render_ics_url_field' ),
			'bn-calendar',
			'bn_calendar_main_section'
		);

		add_settings_field(
			'mobile_default_view',
			__( 'Vue par défaut sur mobile', 'bad-nantes-calendar' ),
			array( $this, 'render_mobile_view_field' ),
			'bn-calendar',
			'bn_calendar_main_section'
		);
	}

	/**
	 * Assainit toutes les entrées avant sauvegarde.
	 *
	 * @param array $input Données brutes du formulaire.
	 * @return array
	 */
	public function sanitize_options( $input ) {
		$output = self::get_options();

		if ( isset( $input['ics_url'] ) ) {
			$url = esc_url_raw( trim( $input['ics_url'] ), array( 'http', 'https', 'webcal' ) );
			// webcal:// est un alias : on le normalise en https:// (le fetch serveur ne gère que http/https).
			$output['ics_url'] = preg_replace( '#^webcal://#i', 'https://', $url );

			// L'URL a changé : on purge le cache du proxy.
			delete_transient( BN_Ics_Proxy::TRANSIENT_KEY );
		}

		if ( isset( $input['mobile_default_view'] ) ) {
			$view = sanitize_text_field( $input['mobile_default_view'] );
			$output['mobile_default_view'] = in_array( $view, array( 'liste', 'mois' ), true ) ? $view : 'liste';
		}

		return $output;
	}

	/**
	 * Texte d'introduction de la section.
	 */
	public function render_section_intro() {
		echo '<p>' . esc_html__( "Collez l'URL publique du flux iCal (ICS) de votre agenda. Aucune clé API n'est nécessaire. Consultez le README pour trouver cette URL (Google, Outlook, Nextcloud…).", 'bad-nantes-calendar' ) . '</p>';
	}

	/**
	 * Champ URL du flux ICS.
	 */
	public function render_ics_url_field() {
		$options = self::get_options();
		printf(
			'<input type="url" name="%1$s[ics_url]" id="bn_ics_url" value="%2$s" class="large-text" placeholder="https://calendar.google.com/calendar/ical/.../public/basic.ics" autocomplete="off" />',
			esc_attr( self::OPTION_NAME ),
			esc_attr( $options['ics_url'] )
		);
		echo '<p class="description">' . esc_html__( "Le flux doit être public. Il est récupéré côté serveur (avec cache) : pas de problème de CORS.", 'bad-nantes-calendar' ) . '</p>';
	}

	/**
	 * Champ vue mobile par défaut.
	 */
	public function render_mobile_view_field() {
		$options = self::get_options();
		$current = $options['mobile_default_view'];
		$choices = array(
			'liste' => __( 'Liste', 'bad-nantes-calendar' ),
			'mois'  => __( 'Mois', 'bad-nantes-calendar' ),
		);

		echo '<select name="' . esc_attr( self::OPTION_NAME ) . '[mobile_default_view]" id="bn_mobile_default_view">';
		foreach ( $choices as $value => $label ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $value ),
				selected( $current, $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Vue affichée automatiquement sur petit écran (moins de 600px).', 'bad-nantes-calendar' ) . '</p>';
	}

	/**
	 * Rendu de la page de réglages.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				// settings_fields ajoute le nonce et les champs cachés requis.
				settings_fields( 'bn_calendar_settings_group' );
				do_settings_sections( 'bn-calendar' );
				submit_button();
				?>
			</form>
			<hr />
			<h2><?php esc_html_e( 'Utilisation', 'bad-nantes-calendar' ); ?></h2>
			<p><?php esc_html_e( 'Insérez un des shortcodes suivants dans une page ou un article :', 'bad-nantes-calendar' ); ?></p>
			<p><code>[bn_calendar view="mois"]</code> &nbsp; <code>[bn_calendar view="semaine"]</code></p>
		</div>
		<?php
	}
}
