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
			'api_key'             => '',
			'calendar_id'         => '',
			'slot_min_time'       => '08:00',
			'slot_max_time'       => '23:00',
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
			__( 'Configuration de l\'agenda Google', 'bad-nantes-calendar' ),
			array( $this, 'render_section_intro' ),
			'bn-calendar'
		);

		add_settings_field(
			'api_key',
			__( 'Clé API Google', 'bad-nantes-calendar' ),
			array( $this, 'render_api_key_field' ),
			'bn-calendar',
			'bn_calendar_main_section'
		);

		add_settings_field(
			'calendar_id',
			__( 'ID de l\'agenda Google', 'bad-nantes-calendar' ),
			array( $this, 'render_calendar_id_field' ),
			'bn-calendar',
			'bn_calendar_main_section'
		);

		add_settings_field(
			'slot_min_time',
			__( 'Heure de début (vue semaine)', 'bad-nantes-calendar' ),
			array( $this, 'render_slot_min_field' ),
			'bn-calendar',
			'bn_calendar_main_section'
		);

		add_settings_field(
			'slot_max_time',
			__( 'Heure de fin (vue semaine)', 'bad-nantes-calendar' ),
			array( $this, 'render_slot_max_field' ),
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

		if ( isset( $input['api_key'] ) ) {
			$output['api_key'] = sanitize_text_field( $input['api_key'] );
		}

		if ( isset( $input['calendar_id'] ) ) {
			$output['calendar_id'] = sanitize_text_field( $input['calendar_id'] );
		}

		if ( isset( $input['slot_min_time'] ) ) {
			$output['slot_min_time'] = $this->sanitize_time( $input['slot_min_time'], '08:00' );
		}

		if ( isset( $input['slot_max_time'] ) ) {
			$output['slot_max_time'] = $this->sanitize_time( $input['slot_max_time'], '23:00' );
		}

		if ( isset( $input['mobile_default_view'] ) ) {
			$view = sanitize_text_field( $input['mobile_default_view'] );
			$output['mobile_default_view'] = in_array( $view, array( 'liste', 'mois' ), true ) ? $view : 'liste';
		}

		return $output;
	}

	/**
	 * Valide un champ horaire au format HH:MM, sinon retourne la valeur par défaut.
	 *
	 * @param string $value    Valeur brute.
	 * @param string $fallback Valeur de repli.
	 * @return string
	 */
	private function sanitize_time( $value, $fallback ) {
		$value = sanitize_text_field( $value );
		if ( preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $value ) ) {
			return $value;
		}
		return $fallback;
	}

	/**
	 * Texte d'introduction de la section.
	 */
	public function render_section_intro() {
		echo '<p>' . esc_html__( "Renseignez la clé API Google et l'ID de l'agenda public. Consultez le README pour créer la clé et restreindre son usage.", 'bad-nantes-calendar' ) . '</p>';
	}

	/**
	 * Champ clé API.
	 */
	public function render_api_key_field() {
		$options = self::get_options();
		printf(
			'<input type="text" name="%1$s[api_key]" id="bn_api_key" value="%2$s" class="regular-text" autocomplete="off" />',
			esc_attr( self::OPTION_NAME ),
			esc_attr( $options['api_key'] )
		);
		echo '<p class="description">' . esc_html__( 'Clé API du projet Google Cloud (API Calendar activée).', 'bad-nantes-calendar' ) . '</p>';
	}

	/**
	 * Champ ID d'agenda.
	 */
	public function render_calendar_id_field() {
		$options = self::get_options();
		printf(
			'<input type="text" name="%1$s[calendar_id]" id="bn_calendar_id" value="%2$s" class="regular-text" autocomplete="off" />',
			esc_attr( self::OPTION_NAME ),
			esc_attr( $options['calendar_id'] )
		);
		echo '<p class="description">' . esc_html__( 'Exemple : xxxx@group.calendar.google.com', 'bad-nantes-calendar' ) . '</p>';
	}

	/**
	 * Champ heure de début.
	 */
	public function render_slot_min_field() {
		$options = self::get_options();
		printf(
			'<input type="time" name="%1$s[slot_min_time]" id="bn_slot_min_time" value="%2$s" />',
			esc_attr( self::OPTION_NAME ),
			esc_attr( $options['slot_min_time'] )
		);
	}

	/**
	 * Champ heure de fin.
	 */
	public function render_slot_max_field() {
		$options = self::get_options();
		printf(
			'<input type="time" name="%1$s[slot_max_time]" id="bn_slot_max_time" value="%2$s" />',
			esc_attr( self::OPTION_NAME ),
			esc_attr( $options['slot_max_time'] )
		);
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
