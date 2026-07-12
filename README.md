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
   - **Clé API Google**
   - **ID de l'agenda Google**
   - **Heure de début / de fin** de la vue semaine (défaut 08:00 / 23:00)
   - **Vue par défaut sur mobile** (liste ou mois)

> La clé API n'est **jamais** stockée dans le dépôt Git : elle vit uniquement
> dans les options WordPress, saisie via la page de réglages.

### Rendre l'agenda Google public

1. Ouvrez [Google Agenda](https://calendar.google.com/).
2. Paramètres de l'agenda concerné → **Autorisations d'accès aux événements**.
3. Cochez **« Rendre disponible publiquement »**.
4. Récupérez l'**ID de l'agenda** dans la section *Intégrer l'agenda*
   (format `xxxx@group.calendar.google.com`).

### Créer et restreindre la clé API (Google Cloud Console)

1. Ouvrez la [Google Cloud Console](https://console.cloud.google.com/).
2. Créez (ou choisissez) un projet.
3. **API et services → Bibliothèque** → activez **Google Calendar API**.
4. **API et services → Identifiants → Créer des identifiants → Clé API**.
5. Restreignez la clé (recommandé) :
   - **Restrictions relatives aux API** → limitez à **Google Calendar API**.
   - **Restrictions liées aux applications** → **Référents HTTP (sites web)**
     et ajoutez votre domaine (ex. `https://www.bad-nantes.fr/*`).

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
    class-bn-shortcode.php       # rendu + enqueue conditionnel
  assets/
    css/bn-calendar.css          # charte Bad'Nantes
    js/bn-init.js                # init FullCalendar
    vendor/fullcalendar/         # FullCalendar 6 local
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
