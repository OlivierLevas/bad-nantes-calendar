/**
 * Répéteur « Lieu → contenu HTML » de la page de réglages Bad'Nantes Calendar.
 *
 * Ajoute / supprime des lignes. Les champs utilisent des tableaux parallèles
 * (locations[match][] et locations[html][]) : aucun index à gérer côté JS.
 */
(function () {
	'use strict';

	function ready() {
		var repeater = document.getElementById('bn-locations-repeater');
		var addBtn = document.getElementById('bn-add-location');
		var template = document.getElementById('bn-location-row-template');

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
			if (!e.target.classList.contains('bn-remove-location')) {
				return;
			}
			e.preventDefault();
			var row = e.target.closest('.bn-location-row');
			if (row) {
				row.parentNode.removeChild(row);
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', ready);
	} else {
		ready();
	}
})();
