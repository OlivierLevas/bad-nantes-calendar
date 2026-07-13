<?php
/**
 * Données structurées (schema.org) du club.
 *
 * Décrit Bad'Nantes comme un SportsClub et publie ses créneaux hebdomadaires en
 * openingHoursSpecification, à partir du flux ICS. C'est ce que lisent en
 * priorité les moteurs de recherche et les robots des IA : sans cela, les
 * horaires du club ne sont visibles que dans le calendrier, que rien ne sait
 * interpréter automatiquement.
 *
 * Quand Yoast est présent, on enrichit son graphe existant plutôt que d'émettre
 * un second bloc : deux descriptions concurrentes du même club obligeraient
 * Google à les réconcilier, et se contrediraient à la première divergence.
 *
 * @package Bad_Nantes_Calendar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Publie le schema.org du club.
 */
class BN_Schema {

	/**
	 * Correspondance des jours ISO (1 = lundi) vers les jours schema.org.
	 *
	 * @var array
	 */
	private static $days = array(
		1 => 'https://schema.org/Monday',
		2 => 'https://schema.org/Tuesday',
		3 => 'https://schema.org/Wednesday',
		4 => 'https://schema.org/Thursday',
		5 => 'https://schema.org/Friday',
		6 => 'https://schema.org/Saturday',
		7 => 'https://schema.org/Sunday',
	);

	/**
	 * Se greffe sur Yoast si disponible, sinon publie un bloc autonome.
	 */
	public function __construct() {
		if ( defined( 'WPSEO_VERSION' ) ) {
			add_filter( 'wpseo_schema_graph', array( $this, 'enrich_graph' ) );
			return;
		}

		add_action( 'wp_head', array( $this, 'print_graph' ), 20 );
	}

	/**
	 * Complète le nœud Organization du graphe Yoast.
	 *
	 * @param array $graph Graphe schema.org produit par Yoast.
	 * @return array
	 */
	public function enrich_graph( $graph ) {
		if ( ! is_array( $graph ) ) {
			return $graph;
		}

		$club = $this->club_properties();

		if ( array() === $club ) {
			return $graph;
		}

		foreach ( $graph as $index => $node ) {
			if ( ! isset( $node['@type'] ) ) {
				continue;
			}

			$types = (array) $node['@type'];

			if ( ! in_array( 'Organization', $types, true ) ) {
				continue;
			}

			// Un club de sport reste une organisation : on ajoute le type, sans
			// remplacer celui que Yoast a posé et auquel ses autres nœuds se réfèrent.
			$types[] = 'SportsClub';

			$graph[ $index ]          = array_merge( $node, $club );
			$graph[ $index ]['@type'] = array_values( array_unique( $types ) );
		}

		return $graph;
	}

	/**
	 * Publie un bloc JSON-LD autonome (repli quand Yoast est absent).
	 */
	public function print_graph() {
		$club = $this->club_properties();

		if ( array() === $club ) {
			return;
		}

		$node = array_merge(
			array(
				'@context' => 'https://schema.org',
				'@type'    => array( 'Organization', 'SportsClub' ),
				'@id'      => home_url( '/#organization' ),
				'name'     => get_bloginfo( 'name' ),
				'url'      => home_url( '/' ),
			),
			$club
		);

		printf(
			'<script type="application/ld+json">%s</script>' . "\n",
			wp_json_encode( $node ) // phpcs:ignore WordPress.Security.EscapeOutput -- JSON encodé, inséré dans un script ld+json.
		);
	}

	/**
	 * Propriétés décrivant le club : zone desservie, créneaux, gymnases.
	 *
	 * @return array Vide si le flux ne fournit aucun créneau — mieux vaut ne rien
	 *               déclarer que publier des horaires vides.
	 */
	private function club_properties() {
		$occurrences = BN_Ics_Proxy::get_week_occurrences();

		if ( array() === $occurrences ) {
			return array();
		}

		$properties = array(
			'areaServed'                => array(
				'@type' => 'City',
				'name'  => 'Nantes',
			),
			'openingHoursSpecification' => $this->opening_hours( $occurrences ),
		);

		$places = $this->places();

		if ( array() !== $places ) {
			$properties['location'] = $places;
		}

		return $properties;
	}

	/**
	 * Convertit les créneaux de la semaine en horaires hebdomadaires.
	 *
	 * Les créneaux se répètent d'une semaine à l'autre : la semaine en cours
	 * suffit à décrire le rythme du club. Les doublons (même jour, mêmes heures)
	 * sont fusionnés.
	 *
	 * @param array $occurrences Occurrences issues du flux.
	 * @return array
	 */
	private function opening_hours( array $occurrences ) {
		$hours = array();

		foreach ( $occurrences as $occurrence ) {
			$day = (int) $occurrence['start']->format( 'N' );

			if ( ! isset( self::$days[ $day ] ) ) {
				continue;
			}

			$opens  = $occurrence['start']->format( 'H:i' );
			$closes = $occurrence['end']->format( 'H:i' );

			$hours[ $day . $opens . $closes ] = array(
				'@type'     => 'OpeningHoursSpecification',
				'dayOfWeek' => self::$days[ $day ],
				'opens'     => $opens,
				'closes'    => $closes,
			);
		}

		return array_values( $hours );
	}

	/**
	 * Décrit les gymnases configurés comme des lieux, avec leur adresse.
	 *
	 * @return array
	 */
	private function places() {
		$options = BN_Settings::get_options();
		$places  = array();

		foreach ( (array) $options['locations'] as $loc ) {
			$nom     = isset( $loc['nom'] ) ? trim( $loc['nom'] ) : '';
			$adresse = isset( $loc['adresse'] ) ? trim( $loc['adresse'] ) : '';

			if ( '' === $nom && '' === $adresse ) {
				continue;
			}

			$place = array( '@type' => 'Place' );

			if ( '' !== $nom ) {
				$place['name'] = $nom;
			}

			if ( '' !== $adresse ) {
				$place['address'] = $this->postal_address( $adresse );
			}

			$places[] = $place;
		}

		return $places;
	}

	/**
	 * Découpe une adresse française en PostalAddress.
	 *
	 * Les adresses sont saisies librement dans les réglages (« 29 Rue Paul
	 * Bellamy, 44000 Nantes ») : on isole le code postal et la ville quand la
	 * forme s'y prête, et on retombe sur l'adresse brute sinon.
	 *
	 * @param string $adresse Adresse telle que saisie.
	 * @return array
	 */
	private function postal_address( $adresse ) {
		$address = array(
			'@type'          => 'PostalAddress',
			'addressCountry' => 'FR',
		);

		if ( preg_match( '/^(.*?),\s*(\d{5})\s+(.+)$/u', $adresse, $matches ) ) {
			$address['streetAddress']   = trim( $matches[1] );
			$address['postalCode']      = $matches[2];
			$address['addressLocality'] = trim( $matches[3] );

			return $address;
		}

		$address['streetAddress'] = $adresse;

		return $address;
	}
}
