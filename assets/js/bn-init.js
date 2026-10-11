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
	 * Construit la légende, qui sert aussi de filtre à choix unique.
	 *
	 * Tous les agendas sont visibles au chargement, aucune case n'étant cochée.
	 * Cocher une case n'affiche que son agenda et décoche la précédente ; la
	 * décocher réaffiche tout. Les entrées sont créées en JS : si le script
	 * échoue, on ne laisse pas au visiteur des cases inertes.
	 *
	 * Le filtrage retire et remet les sources plutôt que de masquer leurs
	 * événements en CSS : la grille se recompose alors correctement (dont le
	 * compteur « +N »). Le rechargement est servi par le cache HTTP du proxy.
	 *
	 * @param {Object}      config    Configuration de l'instance.
	 * @param {Object}      calendar  Instance FullCalendar.
	 * @param {Array}       sources   Descripteurs passés à FullCalendar.
	 * @param {HTMLElement} el        Conteneur du calendrier.
	 * @param {Function}    setHidden Rappel (slug, isHidden) notant le masquage.
	 * @return {HTMLElement|null} La légende, ou null s'il n'y en a pas.
	 */
	function buildLegend(config, calendar, sources, el, setHidden) {
		// Avec un seul agenda, il n'y a rien à distinguer ni à filtrer.
		if (sources.length < 2) {
			return null;
		}

		var legend = document.createElement('div');
		legend.className = 'bn-calendar-legend';
		legend.setAttribute('role', 'group');
		if (config.i18n && config.i18n.legendLabel) {
			legend.setAttribute('aria-label', config.i18n.legendLabel);
		}

		var boxes = [];
		// Agenda coché, ou null quand tout est affiché.
		var selected = null;

		/**
		 * Coche ou décoche un agenda, puis aligne les cases et les sources.
		 *
		 * @param {string} slug Identifiant de l'agenda cliqué.
		 */
		function select(slug) {
			selected = selected === slug ? null : slug;

			for (var i = 0; i < sources.length; i++) {
				var id = sources[i].id;
				var visible = null === selected || selected === id;
				var current = calendar.getEventSourceById(id);

				if (visible && !current) {
					calendar.addEventSource(sources[i]);
				} else if (!visible && current) {
					current.remove();
				}

				// Un agenda masqué ne doit plus peser sur l'avertissement « flux
				// injoignable » : sa requête en vol peut encore échouer après coup.
				setHidden(id, !visible);

				boxes[i].setAttribute('aria-checked', selected === id ? 'true' : 'false');
				boxes[i].classList.toggle('is-off', !visible);
			}
		}

		for (var i = 0; i < sources.length; i++) {
			boxes.push(buildLegendBox(config.calendars[i], select));
			legend.appendChild(boxes[i]);
		}

		el.parentNode.insertBefore(legend, el);

		return legend;
	}

	/**
	 * Construit une entrée de légende : case à la couleur de l'agenda + son nom.
	 *
	 * @param {Object}   entry  Agenda ({ slug, label, color }).
	 * @param {Function} select Rappel (slug) appelé au clic.
	 * @return {HTMLElement}
	 */
	function buildLegendBox(entry, select) {
		var button = document.createElement('button');
		button.type = 'button';
		button.className = 'bn-legend-item';
		button.setAttribute('role', 'checkbox');
		button.setAttribute('aria-checked', 'false');

		var box = document.createElement('span');
		box.className = 'bn-legend-box';
		if (entry.color) {
			box.style.setProperty('--bn-legend-color', entry.color);
		}
		button.appendChild(box);

		var label = document.createElement('span');
		label.className = 'bn-legend-label';
		label.textContent = entry.label;
		button.appendChild(label);

		button.addEventListener('click', function () {
			select(entry.slug);
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

		// Le conteneur arrive pré-rempli avec les créneaux de la semaine rendus par
		// PHP (contenu lisible par les moteurs de recherche et les robots des IA).
		// On le vide avant de laisser FullCalendar prendre la place, en gardant ces
		// créneaux de côté : ils reviennent si le calendrier ne peut pas s'afficher.
		var fallbackHtml = el.innerHTML;
		el.textContent = '';

		try {
			startCalendar(el, config, fallbackHtml);
		} catch (e) {
			if (window.console && window.console.error) {
				window.console.error('Bad’Nantes Calendar : calendrier non rendu.', e);
			}
			el.innerHTML = fallbackHtml;
		}
	}

	/**
	 * Rend FullCalendar dans un conteneur vidé de ses créneaux PHP.
	 *
	 * @param {HTMLElement} el           Élément conteneur.
	 * @param {Object}      config       Configuration de l'instance.
	 * @param {string}      fallbackHtml Créneaux rendus par PHP, éventuellement vides.
	 */
	function startCalendar(el, config, fallbackHtml) {
		var currentView = pickView(config);
		var calendar = null;
		var legend = null;

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
		// Vrai dès qu'un flux a répondu : le calendrier a alors quelque chose à montrer.
		var loaded = false;
		// Vrai une fois les créneaux PHP remis à la place du calendrier.
		var restored = false;

		/**
		 * Remet les créneaux PHP à la place d'un calendrier resté vide.
		 */
		function restoreFallback() {
			restored = true;
			calendar.destroy();
			if (legend) {
				legend.parentNode.removeChild(legend);
			}
			if (warningEl) {
				warningEl.parentNode.removeChild(warningEl);
			}
			el.innerHTML = fallbackHtml;
		}

		/**
		 * Note l'état d'un flux et met l'avertissement à jour en conséquence.
		 *
		 * @param {string}  slug Identifiant de l'agenda.
		 * @param {boolean} ok   Vrai si le flux a répondu.
		 */
		function reportSourceState(slug, ok) {
			// Retirer une source n'annule pas sa requête en vol : l'échec d'un agenda
			// que le visiteur vient de masquer ne doit pas ressusciter l'avertissement.
			if (restored || (!ok && hidden[slug])) {
				return;
			}

			if (ok) {
				delete failed[slug];
				// Un agenda masqué est déclaré « ok » sans que son flux ait répondu.
				loaded = loaded || !hidden[slug];
			} else {
				failed[slug] = true;
				if (window.console && window.console.error) {
					window.console.error('Bad’Nantes Calendar : flux ICS injoignable (' + slug + ').');
				}
			}

			var failedCount = 0;
			for (var key in failed) {
				if (Object.prototype.hasOwnProperty.call(failed, key)) {
					failedCount++;
				}
			}
			var broken = failedCount > 0;

			// Aucun flux n'a répondu : plutôt qu'un calendrier vide sous un
			// avertissement, on remet les créneaux PHP. Différé, pour ne pas détruire
			// le calendrier depuis l'un de ses propres rappels.
			if (!loaded && fallbackHtml && failedCount === config.calendars.length) {
				window.setTimeout(function () {
					if (!restored) {
						restoreFallback();
					}
				}, 0);
				return;
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

		calendar = new FullCalendar.Calendar(el, {
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
		legend = buildLegend(config, calendar, sources, el, setSourceHidden);
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
