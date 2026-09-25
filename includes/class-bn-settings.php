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
	 * Couleur proposée par défaut pour un nouveau calendrier (vert club).
	 */
	const DEFAULT_COLOR = '#0d3b32';

	/**
	 * Enregistre les hooks admin.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Charge le petit script des répéteurs (agendas, lieux), seulement sur notre page.
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
			// Liste de tableaux : array( 'slug' => 'entrainements', 'label' => '…', 'url' => 'https://…ics', 'color' => '#rrggbb' ).
			'calendars'           => array(),
			'mobile_default_view' => 'liste',
			// Gabarit HTML unique appliqué à chaque lieu (variables {{nom}} {{adresse}} {{lien}}).
			'location_template'   => self::default_location_template(),
			// Liste de tableaux : array( 'match' => 'texte', 'nom' => '…', 'adresse' => '…', 'lien' => 'https://…' ).
			'locations'           => array(),
		);

		return wp_parse_args( get_option( self::OPTION_NAME, array() ), $defaults );
	}

	/**
	 * Gabarit HTML par défaut : icône Maps cliquable, nom et adresse.
	 *
	 * Markup volontairement dépourvu de classes Gutenberg (wp-block-columns, is-layout-flex,
	 * classes à hash…) : la mise en forme est portée par les seules classes .bn-loc* de
	 * assets/css/bn-calendar.css, donc aucune règle du thème n'a besoin d'être surchargée.
	 *
	 * @return string
	 */
	public static function default_location_template() {
		return <<<'HTML'
<div class="bn-loc">
	<a class="bn-loc-icon" href="{{lien}}" target="_blank" rel="noreferrer noopener">
		<svg aria-hidden="true" focusable="false" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512"><path fill="currentColor" d="M0 188.6C0 84.4 86 0 192 0S384 84.4 384 188.6c0 119.3-120.2 262.3-170.4 316.8-11.8 12.8-31.5 12.8-43.3 0-50.2-54.5-170.4-197.5-170.4-316.8zM192 256a64 64 0 1 0 0-128 64 64 0 1 0 0 128z"/></svg>
	</a>
	<div class="bn-loc-text">
		<span class="bn-loc-name">{{nom}}</span>
		<span class="bn-loc-address">{{adresse}}</span>
	</div>
</div>
HTML;
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

		// Section des agendas : elle vient en premier, c'est le réglage sans lequel
		// le plugin n'affiche rien.
		add_settings_section(
			'bn_calendar_calendars_section',
			__( 'Agendas (flux ICS)', 'bad-nantes-calendar' ),
			array( $this, 'render_calendars_intro' ),
			'bn-calendar'
		);

		add_settings_field(
			'calendars',
			__( 'Agendas', 'bad-nantes-calendar' ),
			array( $this, 'render_calendars_field' ),
			'bn-calendar',
			'bn_calendar_calendars_section'
		);

		add_settings_section(
			'bn_calendar_main_section',
			__( 'Affichage', 'bad-nantes-calendar' ),
			array( $this, 'render_section_intro' ),
			'bn-calendar'
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

		// Réglage mono-agenda des versions <= 1.0.21 : remplacé par 'calendars'.
		unset( $output['ics_url'] );

		// Agendas : tableaux parallèles label[] / url[] / color[] issus du répéteur.
		// Le slug est recalculé ici — il sert d'identifiant au proxy, de clé de cache
		// et d'id de source côté FullCalendar.
		$previous  = isset( $output['calendars'] ) ? (array) $output['calendars'] : array();
		$calendars = array();
		if ( isset( $input['calendars']['url'] ) && is_array( $input['calendars']['url'] ) ) {
			$urls   = $input['calendars']['url'];
			$labels = isset( $input['calendars']['label'] ) && is_array( $input['calendars']['label'] ) ? $input['calendars']['label'] : array();
			$colors = isset( $input['calendars']['color'] ) && is_array( $input['calendars']['color'] ) ? $input['calendars']['color'] : array();
			$known  = isset( $input['calendars']['slug'] ) && is_array( $input['calendars']['slug'] ) ? $input['calendars']['slug'] : array();
			$slugs  = array();

			foreach ( $urls as $i => $raw_url ) {
				$url = esc_url_raw( trim( $raw_url ), array( 'http', 'https', 'webcal' ) );
				// webcal:// est un alias : on le normalise en https:// (le fetch serveur ne gère que http/https).
				$url = preg_replace( '#^webcal://#i', 'https://', $url );

				// Sans flux, la ligne n'a rien à afficher : on l'ignore.
				if ( '' === $url ) {
					continue;
				}

				$label = isset( $labels[ $i ] ) ? sanitize_text_field( $labels[ $i ] ) : '';
				if ( '' === $label ) {
					/* translators: %d : numéro de l'agenda dans la liste. */
					$label = sprintf( __( 'Agenda %d', 'bad-nantes-calendar' ), count( $calendars ) + 1 );
				}

				// Le slug est une identité durable : il est gravé dans les pages déjà
				// servies (…/ics?cal=<slug>) et dans les clés de cache. On conserve
				// donc celui du champ caché, et on n'en dérive un du nom qu'à la
				// première saisie — sinon renommer un agenda casserait le flux pour
				// toutes les pages encore en cache.
				$slug = isset( $known[ $i ] ) ? sanitize_key( $known[ $i ] ) : '';
				if ( '' === $slug ) {
					// sanitize_key après sanitize_title : ce dernier peut laisser des
					// séquences percent-encodées sur un nom non translittérable, que
					// la lecture de ?cal= (même sanitize_key) ne retrouverait pas.
					$slug = sanitize_key( sanitize_title( $label ) );
				}
				if ( '' === $slug ) {
					$slug = 'agenda';
				}

				// Deux agendas peuvent porter le même nom : on dédoublonne pour que le
				// slug reste un identifiant unique.

				$base   = $slug;
				$suffix = 2;
				while ( isset( $slugs[ $slug ] ) ) {
					$slug = $base . '-' . $suffix;
					$suffix++;
				}
				$slugs[ $slug ] = true;

				$color = isset( $colors[ $i ] ) ? sanitize_hex_color( trim( $colors[ $i ] ) ) : '';

				$calendars[] = array(
					'slug'  => $slug,
					'label' => $label,
					'url'   => $url,
					// null si la valeur postée n'est pas une couleur hexadécimale valide :
					// on retombe alors sur la couleur du thème, côté CSS.
					'color' => is_string( $color ) ? $color : '',
				);
			}
		}
		$output['calendars'] = $calendars;

		// Les flux ont pu changer d'URL, de nom (donc de slug) ou disparaître : on
		// purge les caches des agendas enregistrés comme ceux qu'ils remplacent,
		// pour ne pas laisser de transient orphelin ni de contenu périmé.
		BN_Ics_Proxy::purge_cache( array_merge( $previous, $calendars ) );

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
		echo '<p>' . esc_html__( 'Réglages communs à tous les agendas.', 'bad-nantes-calendar' ) . '</p>';
	}

	/**
	 * Introduction de la section « Agendas ».
	 */
	public function render_calendars_intro() {
		echo '<p>' . esc_html__( "Ajoutez autant d'agendas que nécessaire : ils s'affichent tous en même temps dans le calendrier, chacun dans sa couleur. Une légende cliquable permet au visiteur de masquer ceux qui ne l'intéressent pas.", 'bad-nantes-calendar' ) . '</p>';
		echo '<p>' . esc_html__( "Chaque flux doit être public. Aucune clé API n'est nécessaire : les flux sont récupérés côté serveur (avec cache), donc sans problème de CORS. Consultez le README pour trouver l'URL du flux iCal (Google, Outlook, Nextcloud…).", 'bad-nantes-calendar' ) . '</p>';
	}

	/**
	 * Répéteur « Agenda : nom + flux ICS + couleur ».
	 */
	public function render_calendars_field() {
		$options   = self::get_options();
		$empty_row = array( 'label' => '', 'url' => '', 'color' => self::DEFAULT_COLOR, 'slug' => '' );
		$calendars = ! empty( $options['calendars'] ) ? $options['calendars'] : array( $empty_row );

		echo '<div id="bn-calendars-repeater">';
		foreach ( $calendars as $cal ) {
			$cal = wp_parse_args( $cal, $empty_row );
			$this->render_calendar_row( $cal['label'], $cal['url'], $cal['color'], $cal['slug'] );
		}
		echo '</div>';

		echo '<p><button type="button" class="button" id="bn-add-calendar">' . esc_html__( '+ Ajouter un agenda', 'bad-nantes-calendar' ) . '</button></p>';

		// Modèle de ligne vierge cloné par le JS admin.
		echo '<template id="bn-calendar-row-template">';
		$this->render_calendar_row( '', '', self::DEFAULT_COLOR );
		echo '</template>';
	}

	/**
	 * Affiche une ligne du répéteur d'agendas.
	 *
	 * @param string $label Nom affiché dans la légende.
	 * @param string $url   URL du flux ICS.
	 * @param string $color Couleur des événements de cet agenda.
	 * @param string $slug  Identifiant déjà attribué ; vide pour une nouvelle ligne.
	 */
	private function render_calendar_row( $label, $url, $color, $slug = '' ) {
		$name = self::OPTION_NAME;
		?>
		<div class="bn-calendar-row" style="margin-bottom:1em;padding:1em;border:1px solid #dcdcde;background:#fff;max-width:600px;">
			<?php // Identifiant renvoyé tel quel : renommer l'agenda ne doit pas changer l'adresse de son flux. ?>
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>[calendars][slug][]" value="<?php echo esc_attr( $slug ); ?>" />
			<p style="margin-top:0;">
				<label>
					<strong><?php esc_html_e( 'Nom affiché dans la légende', 'bad-nantes-calendar' ); ?></strong><br />
					<input type="text" name="<?php echo esc_attr( $name ); ?>[calendars][label][]" value="<?php echo esc_attr( $label ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Ex. : Entraînements', 'bad-nantes-calendar' ); ?>" />
				</label>
			</p>
			<p>
				<label>
					<strong><?php esc_html_e( 'URL du flux ICS', 'bad-nantes-calendar' ); ?></strong><br />
					<input type="url" name="<?php echo esc_attr( $name ); ?>[calendars][url][]" value="<?php echo esc_attr( $url ); ?>" class="large-text" placeholder="https://calendar.google.com/calendar/ical/.../public/basic.ics" autocomplete="off" />
				</label>
			</p>
			<p>
				<label>
					<strong><?php esc_html_e( 'Couleur des événements', 'bad-nantes-calendar' ); ?></strong><br />
					<input type="color" name="<?php echo esc_attr( $name ); ?>[calendars][color][]" value="<?php echo esc_attr( '' !== $color ? $color : self::DEFAULT_COLOR ); ?>" />
				</label>
			</p>
			<p style="margin-bottom:0;">
				<button type="button" class="button-link bn-remove-calendar" style="color:#b32d2e;"><?php esc_html_e( 'Supprimer cet agenda', 'bad-nantes-calendar' ); ?></button>
			</p>
		</div>
		<?php
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
