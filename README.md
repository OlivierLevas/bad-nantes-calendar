# Bad'Nantes Calendar

Plugin WordPress maison du club de badminton **Bad'Nantes**. Il affiche un
agenda Google **public** via [FullCalendar 6](https://fullcalendar.io/),
en **vue mois** ou **vue semaine** (grille horaire), avec bascule automatique
en **vue liste** sur mobile.

FullCalendar est **embarqué en local** dans le plugin : aucune dépendance à un
CDN au moment de l'exécution.

---

## Installation

1. Récupérez le `.zip` du plugin (voir la section *Release* plus bas) et
   installez-le depuis **Extensions → Ajouter → Téléverser une extension**.
2. Activez le plugin.
3. Allez dans **Réglages → Bad'Nantes Calendar** et renseignez :
   - **URL du flux ICS** (agenda public au format iCal)
   - **Heure de début / de fin** de la vue semaine (défaut 08:00 / 23:00)
   - **Vue par défaut sur mobile** (liste ou mois)

> **Aucune clé API n'est nécessaire.** Le plugin lit un flux **ICS public**.
> Le flux est récupéré **côté serveur** par un petit proxy PHP (avec cache de
> 15 min), ce qui évite les problèmes de CORS des flux publics (Google, etc.)
> et n'expose aucune donnée sensible.

### Où trouver l'URL du flux ICS ?

Le plugin fonctionne avec n'importe quel agenda exposant un flux iCal public.

**Google Agenda :**
1. Ouvrez [Google Agenda](https://calendar.google.com/).
2. Paramètres de l'agenda concerné → **Autorisations d'accès aux événements**
   → cochez **« Rendre disponible publiquement »**.
3. Toujours dans les paramètres, section **Intégrer l'agenda** → copiez
   l'**Adresse publique au format iCal** (URL se terminant par
   `.../public/basic.ics`).

**Outlook / Office 365 :** *Paramètres → Calendrier → Calendriers partagés →
Publier* → copiez le lien **ICS**.

**Nextcloud / Framagenda :** menu de l'agenda → **Lien de partage** →
**S'abonner via un lien** (`.ics`).

Collez cette URL dans le champ **URL du flux ICS** des réglages. Elle peut
aussi commencer par `webcal://` (normalisée automatiquement en `https://`).

---

## Usage

Insérez l'un des shortcodes dans une page ou un article. Dans **Gutenberg**,
ajoutez un **bloc « Code court »** (Shortcode) puis collez :

```text
[bn_calendar view="mois"]
```

```text
[bn_calendar view="semaine"]
```

- `view="mois"` → vue `dayGridMonth`
- `view="semaine"` → vue `timeGridWeek`
- Attribut absent → `mois` par défaut

Le calendrier ne s'affiche pas dans l'éditeur : utilisez la
**prévisualisation** ou le **front**. Plusieurs shortcodes peuvent coexister
sur une même page (chaque conteneur a un identifiant unique).

---

## Développement

Arborescence :

```text
bad-nantes-calendar/
  bad-nantes-calendar.php        # fichier principal (en-tête + logique)
  includes/
    class-bn-settings.php        # page de réglages (Settings API)
    class-bn-ics-proxy.php       # proxy ICS serveur (fetch + cache, anti-CORS)
    class-bn-shortcode.php       # rendu + enqueue conditionnel
  assets/
    css/bn-calendar.css          # charte Bad'Nantes
    js/bn-init.js                # init FullCalendar (source iCalendar)
    vendor/fullcalendar/         # FullCalendar 6 + connecteur iCalendar + locale fr
    vendor/ical/                 # ical.js (parseur iCal : RRULE, fuseaux)
  lib/plugin-update-checker/     # mises à jour GitHub (Yahnis Elsts)
  languages/                     # i18n (.pot)
```

Avant toute release, pensez à mettre à jour l'URL du dépôt GitHub dans
`bad-nantes-calendar.php` (constante `BN_CALENDAR_GITHUB_URL`,
marquée d'un `// TODO`).

---

## Release (mise à jour automatique)

Le plugin utilise **Plugin Update Checker v5** avec `enableReleaseAssets()`.
Pour publier une mise à jour :

1. Incrémentez la version dans **deux endroits** :
   - l'en-tête `Version:` de `bad-nantes-calendar.php`
   - la constante `BN_CALENDAR_VERSION`
   - (et `Stable tag` dans `readme.txt`)
2. Commitez, puis créez un tag :
   ```bash
   git tag v1.0.1
   git push origin main --tags
   ```
3. Sur GitHub, créez une **Release** à partir du tag et **attachez le `.zip`**
   du plugin (dossier `bad-nantes-calendar/` zippé). C'est ce zip qui sera
   installé par les sites (release asset).

Les sites détecteront la nouvelle version et proposeront la mise à jour comme
n'importe quelle extension.

---

## Licence

GPLv2 or later — voir `LICENSE`.
