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
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Charge le petit script du répéteur « Lieu → HTML », seulement sur notre page.
	 *
	 * @param string $hook_suffix Identifiant de la page admin courante.
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		if ( 'settings_page_bn-calendar' !== $hook_suffix ) {
			return;
		}
		wp_enqueue_script(
			'bn-admin',
			BN_CALENDAR_URL . 'assets/js/bn-admin.js',
			array(),
			BN_CALENDAR_VERSION,
			true
		);
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
			// Gabarit HTML unique appliqué à chaque lieu (variables {{nom}} {{adresse}} {{lien}}).
			'location_template'   => self::default_location_template(),
			// Liste de tableaux : array( 'match' => 'texte', 'nom' => '…', 'adresse' => '…', 'lien' => 'https://…' ).
			'locations'           => array(),
		);

		return wp_parse_args( get_option( self::OPTION_NAME, array() ), $defaults );
	}

	/**
	 * Gabarit HTML par défaut : nom, adresse et bouton vers Google Maps.
	 *
	 * @return string
	 */
	public static function default_location_template() {
		return "<div class=\"bn-event-place\">\n\t<strong>{{nom}}</strong><br />\n\t{{adresse}}<br />\n\t<a href=\"{{lien}}\" target=\"_blank\" rel=\"noopener\">Voir sur Google Maps</a>\n</div>";
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

		// Section dédiée aux contenus HTML par lieu.
		add_settings_section(
			'bn_calendar_locations_section',
			__( 'Contenu HTML par lieu', 'bad-nantes-calendar' ),
			array( $this, 'render_locations_intro' ),
			'bn-calendar'
		);

		add_settings_field(
			'location_template',
			__( "Gabarit d'affichage", 'bad-nantes-calendar' ),
			array( $this, 'render_location_template_field' ),
			'bn-calendar',
			'bn_calendar_locations_section'
		);

		add_settings_field(
			'locations',
			__( 'Lieux', 'bad-nantes-calendar' ),
			array( $this, 'render_locations_field' ),
			'bn-calendar',
			'bn_calendar_locations_section'
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

		// Gabarit HTML : même sémantique que le contenu d'article (post_content). Un
		// auteur disposant de la capacité unfiltered_html (admin sur site simple) peut
		// enregistrer du HTML riche — blocs Gutenberg, SVG inline… ; sinon on assainit
		// dès la sauvegarde (wp_kses_post retirerait le SVG). Le rendu ne re-filtre pas :
		// la confiance est décidée ici, selon la capacité de l'auteur.
		if ( isset( $input['location_template'] ) ) {
			$template                    = trim( $input['location_template'] );
			$output['location_template'] = current_user_can( 'unfiltered_html' ) ? $template : wp_kses_post( $template );
		}

		// Lieux : tableaux parallèles match[] / nom[] / adresse[] / lien[] issus du répéteur.
		$locations = array();
		if ( isset( $input['locations']['match'] ) && is_array( $input['locations']['match'] ) ) {
			$matches  = $input['locations']['match'];
			$noms     = isset( $input['locations']['nom'] ) && is_array( $input['locations']['nom'] ) ? $input['locations']['nom'] : array();
			$adresses = isset( $input['locations']['adresse'] ) && is_array( $input['locations']['adresse'] ) ? $input['locations']['adresse'] : array();
			$liens    = isset( $input['locations']['lien'] ) && is_array( $input['locations']['lien'] ) ? $input['locations']['lien'] : array();

			foreach ( $matches as $i => $raw_match ) {
				$match   = sanitize_text_field( $raw_match );
				$nom     = isset( $noms[ $i ] ) ? sanitize_text_field( $noms[ $i ] ) : '';
				$adresse = isset( $adresses[ $i ] ) ? sanitize_text_field( $adresses[ $i ] ) : '';
				$lien    = isset( $liens[ $i ] ) ? esc_url_raw( trim( $liens[ $i ] ) ) : '';

				// On ignore les lignes totalement vides.
				if ( '' === $match && '' === $nom && '' === $adresse && '' === $lien ) {
					continue;
				}
				$locations[] = array(
					'match'   => $match,
					'nom'     => $nom,
					'adresse' => $adresse,
					'lien'    => $lien,
				);
			}
		}
		$output['locations'] = $locations;

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
	 * Introduction de la section « Contenu HTML par lieu ».
	 */
	public function render_locations_intro() {
		echo '<p>' . esc_html__( "Le même gabarit d'affichage est appliqué à chaque lieu : vous ne saisissez que le nom, l'adresse et le lien Google Maps. Le bloc s'affiche sous le titre de l'événement quand le champ « Lieu » de l'agenda contient le mot-clé indiqué (insensible à la casse).", 'bad-nantes-calendar' ) . '</p>';
	}

	/**
	 * Champ « Gabarit d'affichage » : HTML commun à tous les lieux.
	 */
	public function render_location_template_field() {
		$options = self::get_options();
		printf(
			'<textarea name="%1$s[location_template]" id="bn_location_template" rows="6" class="large-text code" spellcheck="false">%2$s</textarea>',
			esc_attr( self::OPTION_NAME ),
			esc_textarea( $options['location_template'] )
		);
		echo '<p class="description">' . wp_kses(
			__( 'Variables disponibles : <code>{{nom}}</code>, <code>{{adresse}}</code>, <code>{{lien}}</code> (URL Google Maps). Elles sont remplacées par les valeurs de chaque lieu ci-dessous.', 'bad-nantes-calendar' ),
			array( 'code' => array() )
		) . '</p>';
	}

	/**
	 * Répéteur « Lieu → contenu HTML ».
	 */
	public function render_locations_field() {
		$options   = self::get_options();
		$empty_row = array( 'match' => '', 'nom' => '', 'adresse' => '', 'lien' => '' );
		$locations = ! empty( $options['locations'] ) ? $options['locations'] : array( $empty_row );

		echo '<div id="bn-locations-repeater">';
		foreach ( $locations as $loc ) {
			$loc = wp_parse_args( $loc, $empty_row );
			$this->render_location_row( $loc['match'], $loc['nom'], $loc['adresse'], $loc['lien'] );
		}
		echo '</div>';

		echo '<p><button type="button" class="button" id="bn-add-location">' . esc_html__( '+ Ajouter un lieu', 'bad-nantes-calendar' ) . '</button></p>';

		// Modèle de ligne vierge cloné par le JS admin.
		echo '<template id="bn-location-row-template">';
		$this->render_location_row( '', '', '', '' );
		echo '</template>';
	}

	/**
	 * Affiche une ligne du répéteur (mot-clé de repérage + nom + adresse + lien Maps).
	 *
	 * @param string $match   Mot-clé à repérer dans le lieu de l'événement.
	 * @param string $nom     Nom du lieu affiché ({{nom}}).
	 * @param string $adresse Adresse affichée ({{adresse}}).
	 * @param string $lien    URL Google Maps ({{lien}}).
	 */
	private function render_location_row( $match, $nom, $adresse, $lien ) {
		$name = self::OPTION_NAME;
		?>
		<div class="bn-location-row" style="margin-bottom:1em;padding:1em;border:1px solid #dcdcde;background:#fff;max-width:600px;">
			<p style="margin-top:0;">
				<label>
					<strong><?php esc_html_e( 'Mot-clé à repérer (dans le champ Lieu de l\'agenda)', 'bad-nantes-calendar' ); ?></strong><br />
					<input type="text" name="<?php echo esc_attr( $name ); ?>[locations][match][]" value="<?php echo esc_attr( $match ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Ex. : Victor Hugo', 'bad-nantes-calendar' ); ?>" />
				</label>
			</p>
			<p>
				<label>
					<strong><?php esc_html_e( 'Nom du lieu', 'bad-nantes-calendar' ); ?></strong> <code>{{nom}}</code><br />
					<input type="text" name="<?php echo esc_attr( $name ); ?>[locations][nom][]" value="<?php echo esc_attr( $nom ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Ex. : Gymnase Victor Hugo', 'bad-nantes-calendar' ); ?>" />
				</label>
			</p>
			<p>
				<label>
					<strong><?php esc_html_e( 'Adresse', 'bad-nantes-calendar' ); ?></strong> <code>{{adresse}}</code><br />
					<input type="text" name="<?php echo esc_attr( $name ); ?>[locations][adresse][]" value="<?php echo esc_attr( $adresse ); ?>" class="large-text" placeholder="<?php esc_attr_e( 'Ex. : 12 rue Victor Hugo, 44000 Nantes', 'bad-nantes-calendar' ); ?>" />
				</label>
			</p>
			<p>
				<label>
					<strong><?php esc_html_e( 'Lien Google Maps', 'bad-nantes-calendar' ); ?></strong> <code>{{lien}}</code><br />
					<input type="url" name="<?php echo esc_attr( $name ); ?>[locations][lien][]" value="<?php echo esc_attr( $lien ); ?>" class="large-text" placeholder="https://maps.google.com/?q=..." />
				</label>
			</p>
			<p style="margin-bottom:0;">
				<button type="button" class="button-link bn-remove-location" style="color:#b32d2e;"><?php esc_html_e( 'Supprimer ce lieu', 'bad-nantes-calendar' ); ?></button>
			</p>
		</div>
		<?php
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
