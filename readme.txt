=== Bad'Nantes Calendar ===
Contributors: badnantes
Tags: calendar, google calendar, fullcalendar, agenda, badminton
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.20
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
* Créneaux rendus en HTML côté serveur : lisibles par les moteurs de recherche
  et les robots des IA, qui n'exécutent pas de JavaScript.
* Données structurées `SportsClub` (schema.org) avec les horaires d'ouverture
  déduits du flux ICS et les lieux en `Place`. S'intègre au graphe de Yoast SEO
  s'il est présent, sinon publie son propre bloc JSON-LD.
* Bascule automatique en vue liste sur mobile (moins de 600px), configurable.
* Bloc d'information par lieu (nom, adresse, lien Maps) sous chaque événement,
  via un gabarit HTML unique.
* Charte graphique du club Bad'Nantes.
* Assets chargés uniquement sur les pages contenant le shortcode.
* Mises à jour automatiques depuis GitHub (Plugin Update Checker).

== Installation ==

1. Installez le plugin (zip) puis activez-le.
2. Rendez-vous dans « Réglages → Bad'Nantes Calendar ».
3. Collez l'URL du flux ICS public de votre agenda.
4. Réglez la vue par défaut sur mobile.
5. Renseignez éventuellement les lieux (voir ci-dessous).
6. Insérez un shortcode dans une page :
   `[bn_calendar view="mois"]` ou `[bn_calendar view="semaine"]`.

= Affichage d'un bloc d'information par lieu =

La section « Contenu HTML par lieu » des réglages permet d'afficher un bloc
sous le titre de chaque événement, en fonction de son lieu.

* Le champ « Gabarit d'affichage » contient un **gabarit HTML unique**, commun à
  tous les lieux. Trois variables y sont remplacées à l'affichage :
  `{{nom}}`, `{{adresse}}` et `{{lien}}` (URL Google Maps).
* Le répéteur « Lieux » associe, pour chaque lieu, un texte à rechercher
  (« Correspondance ») aux valeurs de ces trois variables.

La correspondance se fait si le champ `LOCATION` de l'événement **contient** le
texte recherché, sans tenir compte de la casse. Préférez un mot-clé court et
distinctif (par exemple `Victor Hugo`) au libellé complet du lieu.

Un lieu dont aucune variable n'est renseignée n'affiche aucun bloc ; si l'URL
Maps est absente, le lien est retiré du bloc plutôt que rendu vide.

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

= 1.0.20 =
* Créneaux rendus en HTML côté serveur : le conteneur du calendrier était livré
  vide et rempli en JavaScript, donc invisible pour les moteurs de recherche et
  les robots des IA. Il est désormais pré-rempli avec la liste des créneaux de la
  semaine (jour, horaires, gymnase, adresse), que FullCalendar remplace au boot.
  La liste reste affichée si FullCalendar ne charge pas.
* Nouveau parseur iCal en PHP (RFC 5545) : fuseaux, récurrences hebdomadaires
  (BYDAY, INTERVAL, UNTIL, COUNT), EXDATE et événements annulés.
* Données structurées `SportsClub` : horaires d'ouverture déduits du flux ICS,
  zone desservie et gymnases en `Place`. Enrichit le graphe de Yoast SEO s'il est
  actif, sinon publie un bloc JSON-LD autonome.

= 1.0.19 =
* Bloc lieu : icône colorée malgré le `color: inherit` de FullCalendar en vue liste.

= 1.0.18 =
* Bloc lieu : couleur de l'icône préservée en vue liste, ajustements d'icône.

= 1.0.17 =
* Bloc lieu : gabarit et CSS autonomes, sans surcharge des classes Gutenberg.

= 1.0.16 =
* Bloc lieu : suppression du padding horizontal des colonnes.

= 1.0.15 =
* Bloc lieu : suppression du gap entre les colonnes.

= 1.0.14 =
* Bloc lieu : colonnes empilables et adresse non coupée en plein milieu.

= 1.0.13 =
* Vue liste : libellé de date en couleur d'accentuation dans l'en-tête de jour.

= 1.0.12 =
* Vue liste : masquage de la pastille de couleur des événements.

= 1.0.11 =
* Bloc lieu : colonne de l'icône dimensionnée sur son contenu.

= 1.0.10 =
* Événements affichés carrés et sans marge.

= 1.0.9 =
* Ajustements des couleurs du calendrier.

= 1.0.8 =
* Ajustements CSS du bloc lieu et du jour courant.

= 1.0.7 =
* Fond du jour courant en couleur d'accentuation.

= 1.0.6 =
* Le gabarit par lieu accepte du HTML riche : SVG en ligne (icônes Gutenberg ou
  Font Awesome), variables CSS et propriétés de mise en page sont préservées.
* Sécurité : le gabarit est désormais filtré **à la sauvegarde** (sémantique de
  `post_content` : brut si l'auteur a la capacité `unfiltered_html`, `wp_kses_post`
  sinon) et non plus au rendu, où `wp_kses_post` détruisait les SVG. Les valeurs
  injectées dans les variables restent échappées.
* Couleurs du calendrier alignées sur les variables du thème.

= 1.0.5 =
* Le contenu HTML par lieu devient un **gabarit unique** (variables `{{nom}}`,
  `{{adresse}}`, `{{lien}}`) : chaque lieu ne saisit plus que ces valeurs, au lieu
  d'un bloc HTML complet. Le HTML final est produit côté serveur, valeurs échappées.
* Migration : les lieux saisis en 1.0.4 doivent être ressaisis (nom, adresse, lien)
  pour s'afficher de nouveau.

= 1.0.4 =
* Événements : affichage de l'heure de fin en plus de l'heure de début.
* Nouveau réglage « Contenu HTML par lieu » : associez un bloc HTML à un lieu ;
  il s'affiche sous le titre de l'événement quand le lieu correspond.

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
