/**
 * Initialisation de FullCalendar pour Bad'Nantes Calendar.
 *
 * Chaque conteneur .bn-calendar porte sa configuration dans un attribut
 * data-config (JSON). Ce choix évite toute dépendance à l'ordre de chargement
 * des scripts et fonctionne sur tous les thèmes (classiques, FSE, page builders).
 * Aucune donnée sensible : les flux ICS sont publics.
 */
(function () {
	'use strict';

	/**
	 * Détermine la vue à appliquer selon la largeur d'écran.
	 *
	 * @param {Object} config Configuration de l'instance.
	 * @return {string} Nom de vue FullCalendar.
	 */
	function pickView(config) {
		if (window.innerWidth < config.mobileBreakpoint) {
			return config.mobileView;
		}
		return config.initialView;
	}

	/**
	 * Retourne le contenu HTML configuré pour le lieu d'un événement, ou une
	 * chaîne vide. La comparaison est insensible à la casse : on cherche le
	 * texte « match » (déjà en minuscules) dans le lieu de l'événement.
	 *
	 * @param {string} location Lieu de l'événement (extendedProps.location).
	 * @param {Array}  entries  Table [{ match, html }] issue des réglages.
	 * @return {string} HTML ou ''.
	 */
	function locationHtmlFor(location, entries) {
		if (!location || !entries || !entries.length) {
			return '';
		}
		var haystack = location.toLowerCase();
		for (var i = 0; i < entries.length; i++) {
			if (entries[i].match && haystack.indexOf(entries[i].match) !== -1) {
				return entries[i].html || '';
			}
		}
		return '';
	}

	/**
	 * Construit le contenu d'une carte d'événement : heure, titre, puis le
	 * bloc HTML associé au lieu (si configuré).
	 *
	 * @param {Object} arg    Argument fourni par FullCalendar (event, timeText).
	 * @param {Object} config Configuration de l'instance.
	 * @return {HTMLElement}
	 */
	function buildEventNode(arg, config) {
		var wrap = document.createElement('div');
		wrap.className = 'bn-event';

		// Heure début–fin (vide pour les événements « toute la journée »).
		if (arg.timeText) {
			var time = document.createElement('div');
			time.className = 'bn-event-time';
			time.textContent = arg.timeText;
			wrap.appendChild(time);
		}

		// Titre (texte échappé automatiquement via textContent).
		var title = document.createElement('div');
		title.className = 'bn-event-title';
		title.textContent = arg.event.title;
		wrap.appendChild(title);

		// Contenu HTML du lieu. Source : réglages admin, assainie par wp_kses_post.
		var html = locationHtmlFor(arg.event.extendedProps.location, config.locationHtml);
		if (html) {
			var loc = document.createElement('div');
			loc.className = 'bn-event-location';
			loc.innerHTML = html;
			wrap.appendChild(loc);
		}

		return wrap;
	}

	/**
	 * Construit les sources d'événements : un agenda = une source, donc une URL de
	 * proxy distincte (le connecteur iCalendar indexe son cache par URL).
	 *
	 * @param {Object}   config Configuration de l'instance.
	 * @param {Function} report Rappel (slug, ok) signalant l'état d'un flux.
	 * @return {Array} Descripteurs de sources FullCalendar.
	 */
	function buildSources(config, report) {
		var sources = [];

		for (var i = 0; i < config.calendars.length; i++) {
			var calendar = config.calendars[i];
			var source = {
				id: calendar.slug,
				url: calendar.url,
				format: 'ics',
				// État suivi flux par flux : avec plusieurs agendas, un rapport
				// global ferait effacer l'avertissement d'un flux en panne par le
				// premier flux voisin qui répond.
				failure: makeStateReporter(report, calendar.slug, false),
				success: makeStateReporter(report, calendar.slug, true)
			};

			// Sans couleur choisie, on n'en impose aucune : le CSS applique le thème.
			if (calendar.color) {
				source.color = calendar.color;
			}
			sources.push(source);
		}

		return sources;
	}

	/**
	 * Fabrique un rappel de succès/échec pour une source donnée.
	 *
	 * Fonction séparée pour ne pas capturer la variable de boucle de buildSources
	 * (ES5 : `var` n'a pas de portée de bloc).
	 *
	 * @param {Function} report Rappel (slug, ok).
	 * @param {string}   slug   Identifiant de l'agenda.
	 * @param {boolean}  ok     État à signaler.
	 * @return {Function} Rappel à passer à FullCalendar.
	 */
	function makeStateReporter(report, slug, ok) {
		return function (payload) {
			report(slug, ok);
			// success doit retourner les événements bruts ; failure ne retourne rien.
			return ok ? payload : undefined;
		};
	}

	/**
	 * Construit la légende cliquable, qui sert aussi de filtre.
	 *
	 * Tous les agendas sont visibles au chargement ; un clic masque ou remontre
	 * celui de l'entrée. Les entrées sont créées en JS : si le script échoue, on ne
	 * laisse pas au visiteur des boutons inertes.
	 *
	 * @param {Object}      config   Configuration de l'instance.
	 * @param {Object}      calendar Instance FullCalendar.
	 * @param {Array}       sources  Descripteurs passés à FullCalendar.
	 * @param {HTMLElement} el       Conteneur du calendrier.
	 * @param {Function}    setHidden Rappel (slug, isHidden) notant le masquage.
	 */
	function buildLegend(config, calendar, sources, el, setHidden) {
		// Avec un seul agenda, il n'y a rien à distinguer ni à filtrer.
		if (sources.length < 2) {
			return;
		}

		var legend = document.createElement('div');
		legend.className = 'bn-calendar-legend';
		legend.setAttribute('role', 'group');
		if (config.i18n && config.i18n.legendLabel) {
			legend.setAttribute('aria-label', config.i18n.legendLabel);
		}

		for (var i = 0; i < sources.length; i++) {
			legend.appendChild(buildLegendButton(config.calendars[i], sources[i], calendar, setHidden));
		}

		el.parentNode.insertBefore(legend, el);
	}

	/**
	 * Construit une entrée de légende : pastille de couleur + nom de l'agenda.
	 *
	 * Le filtrage retire et remet la source plutôt que de masquer ses événements en
	 * CSS : la grille se recompose alors correctement (dont le compteur « +N »). Le
	 * rechargement est servi par le cache HTTP du proxy.
	 *
	 * @param {Object}   entry     Agenda ({ slug, label, color }).
	 * @param {Object}   source    Descripteur de source correspondant.
	 * @param {Object}   calendar  Instance FullCalendar.
	 * @param {Function} setHidden Rappel (slug, isHidden) notant le masquage.
	 * @return {HTMLElement}
	 */
	function buildLegendButton(entry, source, calendar, setHidden) {
		var button = document.createElement('button');
		button.type = 'button';
		button.className = 'bn-legend-item';
		button.setAttribute('aria-pressed', 'true');

		var dot = document.createElement('span');
		dot.className = 'bn-legend-dot';
		if (entry.color) {
			dot.style.setProperty('--bn-legend-color', entry.color);
		}
		button.appendChild(dot);

		var label = document.createElement('span');
		label.className = 'bn-legend-label';
		label.textContent = entry.label;
		button.appendChild(label);

		button.addEventListener('click', function () {
			var visible = 'true' === button.getAttribute('aria-pressed');

			if (visible) {
				var current = calendar.getEventSourceById(source.id);
				if (current) {
					current.remove();
				}
			} else {
				calendar.addEventSource(source);
			}

			// Un agenda masqué ne doit plus peser sur l'avertissement « flux
			// injoignable » : sa requête en vol peut encore échouer après coup.
			setHidden(source.id, visible);

			button.setAttribute('aria-pressed', visible ? 'false' : 'true');
			button.classList.toggle('is-off', visible);
		});

		return button;
	}

	/**
	 * Initialise un conteneur de calendrier.
	 *
	 * @param {HTMLElement} el Élément conteneur.
	 */
	function initCalendar(el) {
		var raw = el.getAttribute('data-config');
		var config;

		try {
			config = raw ? JSON.parse(raw) : null;
		} catch (e) {
			config = null;
		}

		if (!config) {
			el.textContent = 'Bad’Nantes Calendar : configuration illisible.';
			return;
		}

		// Message clair si la configuration est incomplète (aucun flux ICS enregistré).
		if (!config.configured || !config.calendars || !config.calendars.length) {
			el.textContent = config.i18n && config.i18n.missingConfig ? config.i18n.missingConfig : 'Agenda non configuré.';
			return;
		}

		// FullCalendar absent (script non chargé) : on laisse en place les créneaux
		// rendus par PHP, qui restent lisibles.
		if (typeof FullCalendar === 'undefined') {
			return;
		}

		var currentView = pickView(config);

		// Le conteneur arrive pré-rempli avec les créneaux de la semaine rendus par
		// PHP (contenu lisible par les moteurs de recherche et les robots des IA).
		// On le vide avant de laisser FullCalendar prendre la place.
		el.textContent = '';

		// Avertissement affiché tant qu'au moins un flux est injoignable (proxy en
		// panne, requête bloquée par un filtrage réseau, réponse illisible…). Sans ce
		// message, FullCalendar s'affiche parfaitement vide et sans la moindre
		// explication. On se contente d'avertir au-dessus du calendrier : celui-ci
		// reste vivant, donc la navigation refetche les flux et l'agenda se remplit
		// dès qu'ils répondent.
		var warningEl = null;
		// Agendas actuellement en échec, par slug : l'avertissement disparaît quand
		// le dernier d'entre eux se remet à répondre, pas au premier flux qui répond.
		var failed = {};
		// Agendas masqués depuis la légende : leur état n'a plus à être signalé.
		var hidden = {};

		/**
		 * Note l'état d'un flux et met l'avertissement à jour en conséquence.
		 *
		 * @param {string}  slug Identifiant de l'agenda.
		 * @param {boolean} ok   Vrai si le flux a répondu.
		 */
		function reportSourceState(slug, ok) {
			// Retirer une source n'annule pas sa requête en vol : l'échec d'un agenda
			// que le visiteur vient de masquer ne doit pas ressusciter l'avertissement.
			if (!ok && hidden[slug]) {
				return;
			}

			if (ok) {
				delete failed[slug];
			} else {
				failed[slug] = true;
				if (window.console && window.console.error) {
					window.console.error('Bad’Nantes Calendar : flux ICS injoignable (' + slug + ').');
				}
			}

			var broken = false;
			for (var key in failed) {
				if (Object.prototype.hasOwnProperty.call(failed, key)) {
					broken = true;
					break;
				}
			}

			if (broken && !warningEl) {
				warningEl = document.createElement('p');
				warningEl.className = 'bn-calendar-error';
				warningEl.textContent = (config.i18n && config.i18n.loadError) ||
					'Agenda momentanément indisponible, réessayez plus tard.';
				el.parentNode.insertBefore(warningEl, el);
			} else if (!broken && warningEl) {
				warningEl.parentNode.removeChild(warningEl);
				warningEl = null;
			}
		}

		var sources = buildSources(config, reportSourceState);

		var calendar = new FullCalendar.Calendar(el, {
			initialView: currentView,
			firstDay: config.firstDay,
			locale: config.locale,
			height: 'auto',
			nowIndicator: true,
			// Un événement peut porter un lien (propriété URL de l'ICS) : bloc cliquable.
			eventDisplay: 'block',
			// Affiche l'heure de fin en plus de l'heure de début (format 24h).
			displayEventEnd: true,
			eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
			// Rendu personnalisé : heure début–fin, titre, puis contenu HTML du lieu.
			eventContent: function (arg) {
				return { domNodes: [buildEventNode(arg, config)] };
			},
			// Couleur de l'agenda d'origine, posée en variable CSS sur l'élément de
			// l'événement : le CSS en fait le liseré de la carte. Sans couleur
			// configurée, le liseré garde celle de la bordure.
			eventDidMount: function (arg) {
				if (arg.backgroundColor) {
					arg.el.style.setProperty('--bn-agenda-color', arg.backgroundColor);
				}
			},
			headerToolbar: {
				left: 'prev,next today',
				center: 'title',
				right: 'dayGridMonth,dayGridWeek,listWeek'
			},
			// Une source iCalendar par agenda, servie par le proxy PHP (same-origin,
			// pas de CORS). Elles s'affichent toutes en même temps dans la grille.
			eventSources: sources,
			// Bascule automatique de vue au redimensionnement (desktop <-> mobile).
			windowResize: function () {
				var target = pickView(config);
				if (target !== currentView) {
					currentView = target;
					calendar.changeView(target);
				}
			}
		});

		/**
		 * Note qu'un agenda vient d'être masqué ou remontré depuis la légende.
		 *
		 * @param {string}  slug     Identifiant de l'agenda.
		 * @param {boolean} isHidden Vrai s'il vient d'être masqué.
		 */
		function setSourceHidden(slug, isHidden) {
			if (isHidden) {
				hidden[slug] = true;
				reportSourceState(slug, true);
			} else {
				delete hidden[slug];
			}
		}

		calendar.render();

		// Après render() : la légende se place juste avant le calendrier rendu.
		buildLegend(config, calendar, sources, el, setSourceHidden);
	}

	/**
	 * Initialise tous les calendriers présents sur la page.
	 */
	function boot() {
		var containers = document.querySelectorAll('.bn-calendar');
		for (var i = 0; i < containers.length; i++) {
			initCalendar(containers[i]);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
