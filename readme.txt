=== Bad'Nantes Calendar ===
Contributors: badnantes
Tags: calendar, google calendar, fullcalendar, agenda, badminton
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Affiche un agenda public (flux ICS) du club Bad'Nantes avec FullCalendar 6, en vue semaine ou vue mois. FullCalendar est embarqué en local.

== Description ==

Bad'Nantes Calendar affiche un agenda public au format **iCal (ICS)** via
FullCalendar 6 — compatible Google Agenda, Outlook, Nextcloud, etc.
Deux vues sont disponibles au moyen d'un shortcode :

* Vue mois (grille de jours)
* Vue semaine (grille horaire)

Caractéristiques :

* Aucune clé API : un simple flux ICS public suffit.
* Flux récupéré côté serveur (proxy PHP + cache) : pas de problème de CORS.
* Événements récurrents (RRULE) et fuseaux gérés via ical.js.
* FullCalendar 6 embarqué en local (aucune dépendance CDN au runtime).
* Bascule automatique en vue liste sur mobile (moins de 600px), configurable.
* Charte graphique du club Bad'Nantes.
* Assets chargés uniquement sur les pages contenant le shortcode.
* Mises à jour automatiques depuis GitHub (Plugin Update Checker).

== Installation ==

1. Installez le plugin (zip) puis activez-le.
2. Rendez-vous dans « Réglages → Bad'Nantes Calendar ».
3. Collez l'URL du flux ICS public de votre agenda.
4. Réglez la vue par défaut sur mobile.
5. Insérez un shortcode dans une page :
   `[bn_calendar view="mois"]` ou `[bn_calendar view="semaine"]`.

== Frequently Asked Questions ==

= Où trouver l'URL du flux ICS ? =

Pour Google Agenda : paramètres de l'agenda, section « Intégrer l'agenda »,
champ « Adresse publique au format iCal » (URL se terminant par
`.../public/basic.ics`). Voir le README pour Outlook et Nextcloud.

= L'agenda doit-il être public ? =

Oui, le flux ICS doit être accessible publiquement.

= Faut-il une clé API Google ? =

Non. Le plugin lit uniquement un flux ICS public, sans clé ni compte Google Cloud.

= Peut-on afficher plusieurs calendriers sur une même page ? =

Oui, chaque shortcode génère un conteneur avec un identifiant unique.

= Rien ne s'affiche, pourquoi ? =

Vérifiez que l'URL du flux ICS est renseignée et que le flux est bien public
et accessible depuis le serveur du site.

== Changelog ==

= 1.0.3 =
* Réglages : suppression des champs « Heure de début / de fin (vue semaine) »,
  devenus inutiles depuis le passage de la vue semaine en dayGridWeek.

= 1.0.2 =
* Vue semaine : passage en dayGridWeek (7 colonnes, une par jour, événements
  listés dans chaque colonne) au lieu de la grille horaire timeGridWeek.

= 1.0.1 =
* Correctif : la configuration du calendrier est désormais transmise via un
  attribut data- sur le conteneur (au lieu de wp_localize_script). Corrige le
  calendrier qui restait vide sur les thèmes de blocs (FSE) et page builders.

= 1.0.0 =
* Version initiale : shortcode `[bn_calendar]`, vues mois/semaine/liste,
  source ICS via proxy serveur (sans clé API), page de réglages, charte
  Bad'Nantes, FullCalendar 6 local, mises à jour GitHub.
