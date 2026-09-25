/**
 * Répéteurs de la page de réglages Bad'Nantes Calendar (agendas, lieux).
 *
 * Ajoute / supprime des lignes. Les champs utilisent des tableaux parallèles
 * (calendars[url][], locations[match][]…) : aucun index à gérer côté JS.
 */
(function () {
	'use strict';

	/**
	 * Câble un répéteur : bouton d'ajout qui clone un <template>, et suppression
	 * de ligne par délégation d'événement.
	 *
	 * @param {Object} ids Identifiants et classes du répéteur :
	 *                     { repeater, addButton, template, row, remove }.
	 */
	function initRepeater(ids) {
		var repeater = document.getElementById(ids.repeater);
		var addBtn = document.getElementById(ids.addButton);
		var template = document.getElementById(ids.template);

		if (!repeater || !addBtn || !template) {
			return;
		}

		// Ajout d'une ligne vierge à partir du modèle.
		addBtn.addEventListener('click', function () {
			var clone = template.content.cloneNode(true);
			repeater.appendChild(clone);
		});

		// Suppression d'une ligne (délégation d'événement).
		repeater.addEventListener('click', function (e) {
			if (!e.target.classList.contains(ids.remove)) {
				return;
			}
			e.preventDefault();
			var row = e.target.closest('.' + ids.row);
			if (row) {
				row.parentNode.removeChild(row);
			}
		});
	}

	function ready() {
		initRepeater({
			repeater: 'bn-calendars-repeater',
			addButton: 'bn-add-calendar',
			template: 'bn-calendar-row-template',
			row: 'bn-calendar-row',
			remove: 'bn-remove-calendar'
		});

		initRepeater({
			repeater: 'bn-locations-repeater',
			addButton: 'bn-add-location',
			template: 'bn-location-row-template',
			row: 'bn-location-row',
			remove: 'bn-remove-location'
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', ready);
	} else {
		ready();
	}
})();
