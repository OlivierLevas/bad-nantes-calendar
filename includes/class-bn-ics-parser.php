<?php
/**
 * Parseur ICS minimal pour Bad'Nantes Calendar.
 *
 * Extrait, sur une fenêtre de temps donnée, les occurrences d'un flux iCal.
 * Il ne couvre volontairement que ce dont le club a besoin : des créneaux
 * ponctuels et des récurrences hebdomadaires (FREQ=WEEKLY). Les autres
 * fréquences sont ignorées plutôt qu'approximées — mieux vaut ne rien afficher
 * qu'afficher un horaire faux.
 *
 * Ce fichier est du PHP pur : aucune fonction WordPress, donc testable seul.
 *
 * @package Bad_Nantes_Calendar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Analyse un flux iCal et développe ses occurrences.
 */
class BN_Ics_Parser {

	/**
	 * Durée retenue quand un événement n'a pas de DTEND.
	 */
	const DEFAULT_DURATION = 'PT1H';

	/**
	 * Correspondance des jours iCal vers les jours ISO-8601 (1 = lundi).
	 *
	 * @var array
	 */
	private static $days = array(
		'MO' => 1,
		'TU' => 2,
		'WE' => 3,
		'TH' => 4,
		'FR' => 5,
		'SA' => 6,
		'SU' => 7,
	);

	/**
	 * Retourne les occurrences comprises dans [$from, $to[, triées par date de début.
	 *
	 * @param string            $ics        Contenu du flux iCal.
	 * @param DateTimeImmutable $from       Début de la fenêtre (inclus).
	 * @param DateTimeImmutable $to         Fin de la fenêtre (exclue).
	 * @param DateTimeZone      $display_tz Fuseau dans lequel exprimer les dates rendues.
	 * @return array Liste de array( 'start' => DateTimeImmutable, 'end' => DateTimeImmutable, 'summary' => string, 'location' => string ).
	 */
	public static function occurrences( $ics, DateTimeImmutable $from, DateTimeImmutable $to, DateTimeZone $display_tz ) {
		$occurrences = array();

		foreach ( self::events( (string) $ics ) as $event ) {
			foreach ( self::expand( $event, $from, $to ) as $start ) {
				$occurrences[] = array(
					'start'    => $start->setTimezone( $display_tz ),
					'end'      => $start->add( $event['duration'] )->setTimezone( $display_tz ),
					'summary'  => $event['summary'],
					'location' => $event['location'],
				);
			}
		}

		usort(
			$occurrences,
			static function ( $a, $b ) {
				return $a['start'] <=> $b['start'];
			}
		);

		return $occurrences;
	}

	/**
	 * Découpe le flux en événements exploitables.
	 *
	 * Les événements annulés, sans DTSTART, ou dont la récurrence n'est pas
	 * hebdomadaire sont écartés ici.
	 *
	 * @param string $ics Contenu du flux iCal.
	 * @return array
	 */
	private static function events( $ics ) {
		$lines  = self::unfold( $ics );
		$events = array();
		$block  = null;

		foreach ( $lines as $line ) {
			if ( 'BEGIN:VEVENT' === $line ) {
				$block = array();
				continue;
			}

			if ( null === $block ) {
				continue;
			}

			if ( 'END:VEVENT' === $line ) {
				$event = self::build_event( $block );
				if ( null !== $event ) {
					$events[] = $event;
				}
				$block = null;
				continue;
			}

			$block[] = $line;
		}

		return $events;
	}

	/**
	 * Construit un événement à partir des lignes d'un bloc VEVENT.
	 *
	 * @param array $lines Lignes du bloc.
	 * @return array|null Null si l'événement est inexploitable ou à ignorer.
	 */
	private static function build_event( array $lines ) {
		$props = array();

		foreach ( $lines as $line ) {
			$colon = strpos( $line, ':' );
			if ( false === $colon ) {
				continue;
			}

			$left  = substr( $line, 0, $colon );
			$value = substr( $line, $colon + 1 );

			$parts  = explode( ';', $left );
			$name   = strtoupper( array_shift( $parts ) );
			$params = array();
			foreach ( $parts as $param ) {
				$pair = explode( '=', $param, 2 );
				if ( 2 === count( $pair ) ) {
					$params[ strtoupper( $pair[0] ) ] = $pair[1];
				}
			}

			// EXDATE peut apparaître plusieurs fois : on accumule.
			if ( 'EXDATE' === $name ) {
				$props['EXDATE'][] = array( $params, $value );
				continue;
			}

			$props[ $name ] = array( $params, $value );
		}

		if ( ! isset( $props['DTSTART'] ) ) {
			return null;
		}

		if ( isset( $props['STATUS'] ) && 'CANCELLED' === strtoupper( $props['STATUS'][1] ) ) {
			return null;
		}

		$start = self::to_date( $props['DTSTART'][0], $props['DTSTART'][1] );
		if ( null === $start ) {
			return null;
		}

		$rrule = isset( $props['RRULE'] ) ? self::parse_rrule( $props['RRULE'][1] ) : array();

		// Une récurrence non hebdomadaire n'est pas développée : on ignore
		// l'événement entier plutôt que de n'en afficher que la première date.
		if ( array() !== $rrule && 'WEEKLY' !== $rrule['FREQ'] ) {
			return null;
		}

		return array(
			'start'    => $start,
			'duration' => self::duration( $props, $start ),
			'rrule'    => $rrule,
			'exdates'  => self::exdates( $props ),
			'summary'  => isset( $props['SUMMARY'] ) ? self::unescape( $props['SUMMARY'][1] ) : '',
			'location' => isset( $props['LOCATION'] ) ? self::unescape( $props['LOCATION'][1] ) : '',
		);
	}

	/**
	 * Calcule la durée d'un événement (DTEND - DTSTART), avec repli par défaut.
	 *
	 * @param array             $props Propriétés du VEVENT.
	 * @param DateTimeImmutable $start Début de l'événement.
	 * @return DateInterval
	 */
	private static function duration( array $props, DateTimeImmutable $start ) {
		if ( isset( $props['DTEND'] ) ) {
			$end = self::to_date( $props['DTEND'][0], $props['DTEND'][1] );
			if ( null !== $end && $end > $start ) {
				return $start->diff( $end );
			}
		}

		return new DateInterval( self::DEFAULT_DURATION );
	}

	/**
	 * Rassemble les dates exclues (EXDATE), indexées par instant.
	 *
	 * @param array $props Propriétés du VEVENT.
	 * @return array Clés = timestamps exclus.
	 */
	private static function exdates( array $props ) {
		$excluded = array();

		if ( ! isset( $props['EXDATE'] ) ) {
			return $excluded;
		}

		foreach ( $props['EXDATE'] as $exdate ) {
			list( $params, $value ) = $exdate;

			// Une même ligne EXDATE peut porter plusieurs dates séparées par des virgules.
			foreach ( explode( ',', $value ) as $raw ) {
				$date = self::to_date( $params, $raw );
				if ( null !== $date ) {
					$excluded[ $date->getTimestamp() ] = true;
				}
			}
		}

		return $excluded;
	}

	/**
	 * Développe les occurrences d'un événement sur la fenêtre demandée.
	 *
	 * @param array             $event Événement issu de build_event().
	 * @param DateTimeImmutable $from  Début de la fenêtre (inclus).
	 * @param DateTimeImmutable $to    Fin de la fenêtre (exclue).
	 * @return DateTimeImmutable[] Occurrences (instants de début).
	 */
	private static function expand( array $event, DateTimeImmutable $from, DateTimeImmutable $to ) {
		if ( array() === $event['rrule'] ) {
			$in_window = $event['start'] >= $from && $event['start'] < $to;

			return $in_window ? array( $event['start'] ) : array();
		}

		return self::expand_weekly( $event, $from, $to );
	}

	/**
	 * Développe une récurrence hebdomadaire.
	 *
	 * On itère semaine par semaine depuis DTSTART, en reconstruisant chaque
	 * occurrence à partir de sa date locale et de l'heure locale de DTSTART :
	 * l'heure affichée reste ainsi la même de part et d'autre d'un changement
	 * d'heure (un créneau de 18h reste à 18h en hiver).
	 *
	 * @param array             $event Événement issu de build_event().
	 * @param DateTimeImmutable $from  Début de la fenêtre (inclus).
	 * @param DateTimeImmutable $to    Fin de la fenêtre (exclue).
	 * @return DateTimeImmutable[]
	 */
	private static function expand_weekly( array $event, DateTimeImmutable $from, DateTimeImmutable $to ) {
		$rrule = $event['rrule'];
		$start = $event['start'];
		$tz    = $start->getTimezone();
		$time  = $start->format( 'H:i:s' );

		$interval = max( 1, (int) ( isset( $rrule['INTERVAL'] ) ? $rrule['INTERVAL'] : 1 ) );
		$until    = isset( $rrule['UNTIL'] ) ? self::to_date( array(), $rrule['UNTIL'] ) : null;
		$count    = isset( $rrule['COUNT'] ) ? (int) $rrule['COUNT'] : null;
		$weekdays = self::weekdays( $rrule, $start );

		// Le décompte des semaines part du lundi de la semaine de DTSTART : c'est
		// lui qui fixe la phase d'un INTERVAL supérieur à 1.
		$week   = $start->modify( 'monday this week' )->setTime( 0, 0 );
		$cursor = $week;

		$occurrences = array();
		$emitted     = 0;

		// La fenêtre est finie, et COUNT/UNTIL bornent le reste : la boucle termine.
		while ( $cursor < $to ) {
			foreach ( $weekdays as $weekday ) {
				$date = $cursor->modify( '+' . ( $weekday - 1 ) . ' day' );

				// Reconstruction depuis l'heure locale : évite qu'un +7 jours en
				// secondes ne décale l'horaire au passage à l'heure d'hiver.
				$occurrence = new DateTimeImmutable( $date->format( 'Y-m-d' ) . ' ' . $time, $tz );

				if ( $occurrence < $start ) {
					continue;
				}

				if ( null !== $until && $occurrence > $until ) {
					return $occurrences;
				}

				if ( null !== $count && $emitted >= $count ) {
					return $occurrences;
				}

				++$emitted;

				if ( isset( $event['exdates'][ $occurrence->getTimestamp() ] ) ) {
					continue;
				}

				if ( $occurrence >= $from && $occurrence < $to ) {
					$occurrences[] = $occurrence;
				}
			}

			$cursor = $cursor->modify( '+' . ( 7 * $interval ) . ' day' );
		}

		return $occurrences;
	}

	/**
	 * Jours de la semaine (ISO) portés par la règle, triés.
	 *
	 * Sans BYDAY, la récurrence tombe le jour de DTSTART.
	 *
	 * @param array             $rrule Règle analysée.
	 * @param DateTimeImmutable $start DTSTART de l'événement.
	 * @return int[]
	 */
	private static function weekdays( array $rrule, DateTimeImmutable $start ) {
		if ( ! isset( $rrule['BYDAY'] ) ) {
			return array( (int) $start->format( 'N' ) );
		}

		$weekdays = array();
		foreach ( explode( ',', $rrule['BYDAY'] ) as $day ) {
			// BYDAY peut être préfixé d'un rang (« 2TU ») : sans objet en hebdomadaire.
			$day = strtoupper( substr( $day, -2 ) );
			if ( isset( self::$days[ $day ] ) ) {
				$weekdays[] = self::$days[ $day ];
			}
		}

		sort( $weekdays );

		return $weekdays ? $weekdays : array( (int) $start->format( 'N' ) );
	}

	/**
	 * Analyse une valeur RRULE en table clé => valeur.
	 *
	 * @param string $value Valeur brute de la propriété RRULE.
	 * @return array Table des paramètres, FREQ toujours présent.
	 */
	private static function parse_rrule( $value ) {
		$rrule = array();

		foreach ( explode( ';', $value ) as $part ) {
			$pair = explode( '=', $part, 2 );
			if ( 2 === count( $pair ) ) {
				$rrule[ strtoupper( $pair[0] ) ] = $pair[1];
			}
		}

		if ( ! isset( $rrule['FREQ'] ) ) {
			return array();
		}

		$rrule['FREQ'] = strtoupper( $rrule['FREQ'] );

		return $rrule;
	}

	/**
	 * Convertit une valeur de date iCal en DateTimeImmutable.
	 *
	 * Gère les trois formes rencontrées : heure locale avec TZID, instant UTC
	 * suffixé « Z », et date seule (VALUE=DATE).
	 *
	 * @param array  $params Paramètres de la propriété (TZID, VALUE…).
	 * @param string $value  Valeur brute.
	 * @return DateTimeImmutable|null Null si la valeur est illisible.
	 */
	private static function to_date( array $params, $value ) {
		$value = trim( $value );

		try {
			if ( isset( $params['TZID'] ) ) {
				return new DateTimeImmutable( $value, new DateTimeZone( $params['TZID'] ) );
			}

			// Date seule : le créneau couvre la journée, on part de minuit UTC.
			if ( 8 === strlen( $value ) ) {
				return new DateTimeImmutable( $value, new DateTimeZone( 'UTC' ) );
			}

			return new DateTimeImmutable( $value, new DateTimeZone( 'UTC' ) );
		} catch ( Exception $e ) {
			return null;
		}
	}

	/**
	 * Recolle les lignes repliées et retourne les lignes du flux.
	 *
	 * RFC 5545 : une ligne trop longue est coupée, la suite étant préfixée d'un
	 * espace ou d'une tabulation.
	 *
	 * @param string $ics Contenu du flux.
	 * @return string[]
	 */
	private static function unfold( $ics ) {
		$ics = str_replace( array( "\r\n ", "\r\n\t", "\n ", "\n\t" ), '', $ics );

		$lines = preg_split( '/\r\n|\n|\r/', $ics );

		return array_values(
			array_filter(
				array_map( 'trim', (array) $lines ),
				static function ( $line ) {
					return '' !== $line;
				}
			)
		);
	}

	/**
	 * Restitue un texte iCal échappé (virgules, points-virgules, sauts de ligne).
	 *
	 * @param string $value Valeur brute.
	 * @return string
	 */
	private static function unescape( $value ) {
		return str_replace(
			array( '\\n', '\\N', '\\,', '\\;', '\\\\' ),
			array( "\n", "\n", ',', ';', '\\' ),
			trim( $value )
		);
	}
}
