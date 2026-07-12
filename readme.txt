=== Bad'Nantes Calendar ===
Contributors: badnantes
Tags: calendar, google calendar, fullcalendar, agenda, badminton
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Affiche l'agenda Google public du club Bad'Nantes avec FullCalendar 6, en vue semaine ou vue mois. FullCalendar est embarqué en local.

== Description ==

Bad'Nantes Calendar affiche un agenda Google public via FullCalendar 6.
Deux vues sont disponibles au moyen d'un shortcode :

* Vue mois (grille de jours)
* Vue semaine (grille horaire)

Caractéristiques :

* FullCalendar 6 embarqué en local (aucune dépendance CDN au runtime).
* Bascule automatique en vue liste sur mobile (moins de 600px), configurable.
* Charte graphique du club Bad'Nantes.
* Assets chargés uniquement sur les pages contenant le shortcode.
* Mises à jour automatiques depuis GitHub (Plugin Update Checker).

== Installation ==

1. Installez le plugin (zip) puis activez-le.
2. Rendez-vous dans « Réglages → Bad'Nantes Calendar ».
3. Saisissez la clé API Google et l'ID de l'agenda public.
4. Réglez les heures de début/fin de la vue semaine et la vue mobile par défaut.
5. Insérez un shortcode dans une page :
   `[bn_calendar view="mois"]` ou `[bn_calendar view="semaine"]`.

== Frequently Asked Questions ==

= Où trouver l'ID de l'agenda ? =

Dans les paramètres de l'agenda Google, section « Intégrer l'agenda »,
champ « ID de l'agenda » (souvent au format xxxx@group.calendar.google.com).

= L'agenda doit-il être public ? =

Oui. Dans les paramètres de l'agenda Google, rendez-le public
(« Rendre disponible publiquement »).

= Peut-on afficher plusieurs calendriers sur une même page ? =

Oui, chaque shortcode génère un conteneur avec un identifiant unique.

= Rien ne s'affiche, pourquoi ? =

Vérifiez que la clé API et l'ID d'agenda sont renseignés, que l'API
Google Calendar est activée dans Google Cloud, et que l'agenda est public.

== Changelog ==

= 1.0.0 =
* Version initiale : shortcode `[bn_calendar]`, vues mois/semaine/liste,
  page de réglages, charte Bad'Nantes, FullCalendar 6 local, mises à jour GitHub.
