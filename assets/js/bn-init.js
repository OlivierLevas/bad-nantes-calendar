/**
 * Initialisation de FullCalendar pour Bad'Nantes Calendar.
 *
 * Chaque conteneur .bn-calendar porte sa configuration dans un attribut
 * data-config (JSON). Ce choix évite toute dépendance à l'ordre de chargement
 * des scripts et fonctionne sur tous les thèmes (classiques, FSE, page builders).
 * Aucune donnée sensible : le flux ICS est public.
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
	 * Construit le contenu d'une pastille d'événement : heure, titre, puis le
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

		// Message clair si la configuration est incomplète (aucune URL ICS enregistrée).
		if (!config.configured || !config.icsUrl) {
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

		// Avertissement affiché en cas de flux injoignable, retiré dès qu'il répond.
		// Mémorisé ici pour n'en afficher qu'un seul, quel que soit le nombre de refetchs.
		var warningEl = null;

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
			headerToolbar: {
				left: 'prev,next today',
				center: 'title',
				right: 'dayGridMonth,dayGridWeek,listWeek'
			},
			// Source iCalendar servie par le proxy PHP (same-origin, pas de CORS).
			events: {
				url: config.icsUrl,
				format: 'ics'
			},
			// Flux injoignable (proxy en panne, requête bloquée par un filtrage
			// réseau, réponse illisible…). Sans ce message, FullCalendar s'affiche
			// parfaitement vide et sans la moindre explication. On se contente
			// d'avertir au-dessus du calendrier : celui-ci reste vivant, donc la
			// navigation refetche le flux et l'agenda se remplit dès qu'il répond.
			eventSourceFailure: function (error) {
				if (window.console && window.console.error) {
					window.console.error('Bad’Nantes Calendar : flux ICS injoignable.', error);
				}

				if (warningEl) {
					return;
				}

				warningEl = document.createElement('p');
				warningEl.className = 'bn-calendar-error';
				warningEl.textContent = (config.i18n && config.i18n.loadError) ||
					'Agenda momentanément indisponible, réessayez plus tard.';
				el.parentNode.insertBefore(warningEl, el);
			},
			// Le flux répond de nouveau (navigation, refetch) : on retire l'avertissement.
			eventSourceSuccess: function (rawEvents) {
				if (warningEl) {
					warningEl.parentNode.removeChild(warningEl);
					warningEl = null;
				}
				return rawEvents;
			},
			// Bascule automatique de vue au redimensionnement (desktop <-> mobile).
			windowResize: function () {
				var target = pickView(config);
				if (target !== currentView) {
					currentView = target;
					calendar.changeView(target);
				}
			}
		});

		calendar.render();
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
