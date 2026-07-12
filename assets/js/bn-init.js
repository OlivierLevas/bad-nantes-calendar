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

		var currentView = pickView(config);

		var calendar = new FullCalendar.Calendar(el, {
			initialView: currentView,
			firstDay: config.firstDay,
			locale: config.locale,
			slotMinTime: config.slotMinTime,
			slotMaxTime: config.slotMaxTime,
			height: 'auto',
			nowIndicator: true,
			// Un événement peut porter un lien (propriété URL de l'ICS) : bloc cliquable.
			eventDisplay: 'block',
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
