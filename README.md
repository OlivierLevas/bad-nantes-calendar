# Bad'Nantes Calendar

Plugin WordPress maison du club de badminton **Bad'Nantes**. Il affiche un ou
plusieurs agendas **publics** via [FullCalendar 6](https://fullcalendar.io/),
en **vue mois** ou **vue semaine**, avec bascule automatique en **vue liste**
sur mobile.

Les agendas s'affichent **tous en même temps dans la même grille**, chacun dans
sa couleur. Une **légende à cases à cocher** au-dessus du calendrier les nomme
et sert de filtre à choix unique : tout est visible au chargement ; cocher un
agenda n'affiche que lui, le décocher réaffiche tout.

FullCalendar est **embarqué en local** dans le plugin : aucune dépendance à un
CDN au moment de l'exécution.

---

## Installation

1. Récupérez le `.zip` du plugin (voir la section *Release* plus bas) et
   installez-le depuis **Extensions → Ajouter → Téléverser une extension**.
2. Activez le plugin.
3. Allez dans **Réglages → Bad'Nantes Calendar** et renseignez :
   - **Agendas** : autant de lignes que d'agendas à afficher, chacune avec un
     **nom** (celui de la légende), l'**URL de son flux ICS** et une **couleur**
   - **Vue par défaut sur mobile** (liste ou mois)

> ⚠️ **Mise à jour depuis une version antérieure à la 1.1.0 :** les agendas
> doivent être **ressaisis**. L'ancien réglage « URL du flux ICS » n'est pas
> repris automatiquement, et le calendrier reste vide tant qu'aucun agenda n'est
> enregistré.

> **Aucune clé API n'est nécessaire.** Le plugin lit des flux **ICS publics**.
> Chaque flux est récupéré **côté serveur** par un petit proxy PHP (avec cache de
> 15 min par agenda), ce qui évite les problèmes de CORS des flux publics
> (Google, etc.) et n'expose aucune donnée sensible.

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

Collez cette URL dans le champ **URL du flux ICS** de la ligne d'agenda
correspondante. Elle peut aussi commencer par `webcal://` (normalisée
automatiquement en `https://`). Répétez l'opération pour chaque agenda.

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
- `view="semaine"` → vue `dayGridWeek`
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
