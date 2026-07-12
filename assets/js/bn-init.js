/**
 * Initialisation de FullCalendar pour Bad'Nantes Calendar.
 *
 * Chaque conteneur .bn-calendar possède un attribut data-instance qui pointe
 * vers un objet de configuration localisé par WordPress
 * (window.bnCalendarConfig_<instance>). Aucune clé n'est codée en dur ici.
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
		var instance = el.getAttribute('data-instance');
		var config = window['bnCalendarConfig_' + instance];

		if (!config) {
			return;
		}

		// Message clair si la configuration est incomplète.
		if (!config.apiKey || !config.calendarId) {
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
			// Un événement peut porter un lien Google : on garde l'affichage en bloc cliquable.
			eventDisplay: 'block',
			headerToolbar: {
				left: 'prev,next today',
				center: 'title',
				right: 'dayGridMonth,timeGridWeek,listWeek'
			},
			googleCalendarApiKey: config.apiKey,
			events: {
				googleCalendarId: config.calendarId
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
