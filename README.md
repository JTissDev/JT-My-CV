# Mon CV Web — Julien Tissier

![PHP](https://img.shields.io/badge/PHP-%E2%89%A5%208.0-777BB4?logo=php&logoColor=white)
![SCSS](https://img.shields.io/badge/style-SCSS-CC6699?logo=sass&logoColor=white)
![Version](https://img.shields.io/badge/version-1.1.0--SNAPSHOT-blue)

Site personnel de **Julien Tissier**, développeur informatique : présentation, parcours, créations, CV imprimable (A4 / PDF) et formulaire de contact.

Le site est **bilingue (FR / EN)**, **sans base de données** et entièrement piloté par des fichiers JSON.

Production : <https://www.jtissier-developpeur-informatique.fr>

---

## Sommaire

- [Fonctionnalités](#fonctionnalités)
- [Stack technique](#stack-technique)
- [Prérequis](#prérequis)
- [Installation locale](#installation-locale)
- [Architecture](#architecture)
- [Routage et URLs](#routage-et-urls)
- [Gestion des données](#gestion-des-données)
- [Styles (SCSS)](#styles-scss)
- [Module d'impression et PDF](#module-dimpression-et-pdf)
- [Déploiement](#déploiement)
- [Guides rapides](#guides-rapides)
- [Feuille de route](#feuille-de-route)
- [Versions](#versions)
- [Licence](#licence)

---

## Fonctionnalités

- **Bilingue FR / EN** : URLs propres et localisées, bascule de langue qui conserve la page courante.
- **Contenu piloté par JSON** : textes traduits séparés des données techniques, avec interpolation de variables (`{name}`, `{site_url}`…).
- **Accueil** : présentation du site, présentation personnelle, accès rapides vers le parcours et les créations, compétences techniques.
- **Parcours** : frise chronologique générée depuis le registre (expériences, études, projets), avec regroupement des éléments liés (ex. alternance).
- **Créations** : grille de projets et fiches détaillées (`?creation=<id>`).
- **CV imprimable** : choix du profil (`informatique`, `commerce`, `industrie`) qui adapte compétences, certifications et niveau de détail du parcours ; impression A4 depuis le navigateur.
- **Export PDF** : génération via Dompdf (en cours de stabilisation, voir [feuille de route](#feuille-de-route)).
- **Contact** : formulaire avec validation, envoi via PHPMailer, motifs et messages localisés.
- **Pages légales** : mentions légales, politique de confidentialité, CGU / CGV.
- **SEO** : `sitemap.xml` avec `hreflang`, `robots.txt`, balise canonique, meta description et Open Graph, favicon.

## Stack technique

| Domaine        | Technologies                                         |
| -------------- | ---------------------------------------------------- |
| Back-end       | PHP ≥ 8.0, architecture MVC maison, POO              |
| Front-end      | HTML5, SCSS (méthodologie BEM), JavaScript natif     |
| Données        | JSON (`registre.json`, `cv_fr.json`, `cv_en.json`)   |
| Bibliothèques  | `dompdf/dompdf` ^3.1, `phpmailer/phpmailer` ^7.1     |
| Serveur        | Apache + `mod_rewrite`                               |
| CI/CD          | GitHub Actions → SFTP (lftp) vers IONOS              |

## Prérequis

- PHP ≥ 8.0 avec les extensions `dom`, `mbstring`, `iconv`, `ctype`, `filter`, `hash` (`gd` recommandée pour les images dans les PDF, `openssl` pour un envoi SMTP sécurisé)
- [Composer](https://getcomposer.org/) 2
- Apache avec `mod_rewrite` activé et `AllowOverride All`
- Un compilateur Sass (Dart Sass, Live Sass Compiler dans VS Code, etc.)

## Installation locale

Environnement de référence : **Laragon** sous Windows.

1. Cloner le dépôt dans le dossier `www` de Laragon (par défaut `C:\laragon\www\`) en le nommant **`JT-Developpeur`**. Ce nom doit correspondre au `RewriteBase` du `.htaccess`.
2. Installer les dépendances :

   ```bash
   cd JT-Developpeur
   composer install
   ```

3. Vérifier le `RewriteBase` dans `.htaccess` (voir ci-dessous).
4. Ouvrir <http://localhost/JT-Developpeur/accueil/fr>.

### Configuration du `.htaccess`

Une seule ligne `RewriteBase` doit être active selon l'environnement :

| Environnement | Ligne à activer                 |
| ------------- | ------------------------------- |
| Local         | `RewriteBase /JT-Developpeur/`  |
| Production    | `RewriteBase /`                 |

## Architecture

Le point d'entrée unique est `index.php` (front controller). Il charge le registre et le fichier de langue, résout la page demandée, exécute le contrôleur associé, puis assemble le gabarit.

```
/
├── .github/workflows/deploy.yml   # Déploiement SFTP automatique vers IONOS
├── api/
│   └── download_pdf.php           # Export PDF (Dompdf)
├── assets/                        # Polices, images, icônes
├── classes/
│   ├── careerClasses
│       ├── CareerManager.php          # Construit les éléments du parcours (registre + langue)
│       ├── EvenementParcours.php      # Classe abstraite du parcours
│       ├── Experience.php
│       ├── Etude.php
│       ├── ProjectPersonnel.php
│   └── components/                # Composants de vue orientés objet
│       ├── Button.php             # Button (abstraite), LinkButton, FilterButton, ActionButton
│       ├── HeroPresComponent.php
│       └── SitePresComponent.php
├── data/
│   ├── registre.json              # Données invariantes (langue-indépendantes)
│   ├── cv_fr.json                 # Textes français
│   └── cv_en.json                 # Textes anglais
├── print/                         # Module CV A4 (index.php, helpers/, views/, styles/)
├── scripts/
│   └── print.js                   # Sélection de profil, impression, téléchargement PDF
├── src/
│   ├── controllers/               # 404, career, contactUs, creations, home, legals
│   └── helpers/                   # registry-helpers, header-data, footer-data
├── styles/
│   ├── src/                       # Sources SCSS (abstract, base, layout, components, pages)
│   ├── pages/                     # CSS compilés par page
│   └── main.css                   # CSS global compilé
├── templates/
│   ├── components/                # head, header, footer, page-title
│   ├── pages/                     # Une vue par page
│   └── layout.php                 # Gabarit principal
├── vendor/                        # Dépendances Composer
├── CHANGELOG.md
├── TODO.md
├── composer.json
├── composer.lock
├── robots.txt
├── sitemap.xml
└── index.php
```

### Principes

- **MVC** : les contrôleurs préparent toutes les variables, les vues restent « muettes » (affichage uniquement).
- **Garde d'accès direct** : chaque contrôleur et chaque vue vérifie la constante `APP_STARTED` définie par `index.php`.
- **Composants objets** : boutons et blocs de présentation sont des classes PHP (`render()`).
- **Thèmes** : variables CSS clair / sombre définies dans `styles/src/abstract/_color.scss`.

## Routage et URLs

Format : `/{slug}/{lang}` avec `lang` ∈ `fr`, `en`. Le `.htaccess` réécrit vers `index.php?page={slug}&lang={lang}` ; le slug est résolu en clé interne via `meta.slugs` de `registre.json`. Une page inconnue renvoie la page 404.

| Page                     | Clé interne       | Slug FR                        | Slug EN                |
| ------------------------ | ----------------- | ------------------------------ | ---------------------- |
| Accueil                  | `home`            | `accueil`                      | `home`                 |
| Parcours                 | `career`          | `mon-parcours`                 | `career`               |
| Créations                | `creations`       | `mon-portfolio`                | `portefolio`           |
| Impression du CV         | `print`           | `impressions`                  | `print-resume`         |
| Contact                  | `contactUs`       | `me-contacter`                 | `contact-us`           |
| Mentions légales         | `legals`          | `mentions-legales`             | `legal-notice`         |
| Politique de confidentialité | `confidentialite` | `politique-de-confidentialite` | `privacy-policy`       |
| CGU / CGV                | `cgu-cgv`         | `conditions-generales`         | `terms-and-conditions` |

Les URLs sont générées par le helper `getCleanUrl($pageKey, $lang)` (`src/helpers/registry-helpers.php`) à partir des slugs définis dans `cv_fr.json` / `cv_en.json` (`meta.pages.<clé>.name`).

Fiche d'une création : `/{slug-creations}/{lang}?creation={id}`.

## Gestion des données

| Fichier                  | Contenu                                                                                               |
| ------------------------ | ----------------------------------------------------------------------------------------------------- |
| `data/registre.json`     | `meta` (identité, URLs, slugs), contacts, compétences, certifications, langues, parcours, projets, centres d'intérêt, configuration du formulaire |
| `data/cv_fr.json`        | Tous les textes en français (navigation, pages, parcours, compétences, pages légales…)                 |
| `data/cv_en.json`        | Équivalent anglais, **même structure de clés**                                                        |

**Interpolation** : toute chaîne contenant `{cle}` est remplacée par la valeur correspondante de `registre.meta` (`{name}`, `{site_url}`, `{dev_mail}`, `{dev_adress}`, `{github}`, etc.), y compris dans le registre lui-même.

**Règle** : une clé ajoutée dans `cv_fr.json` doit l'être aussi dans `cv_en.json`.

## Styles (SCSS)

Sources dans `styles/src/`, CSS compilés dans `styles/main.css` (global) et `styles/pages/*.css` (un fichier par page, chargé via `$pageCssMap` dans `index.php`).

Exemple de compilation avec Dart Sass :

```bash
sass styles/src/main.scss styles/main.css
sass styles/src/pages:styles/pages
```

> Le déploiement n'exécute aucune compilation : les CSS compilés doivent être versionnés. Les fichiers `*.scss` et `*.map` ne sont pas envoyés en production.

## Module d'impression et PDF

- `print/index.php` charge les données, filtre compétences, certifications et parcours selon le profil (`?profil=informatique|commerce|industrie`) et la langue (`?lang=fr|en`), puis rend la vue `print/views/cv_a4_default.php`.
- Les éléments du parcours hors profil sont affichés en version réduite (sans description).
- Sur la page d'impression, le CV est prévisualisé dans une `iframe` ; `scripts/print.js` gère le changement de profil (en conservant la langue), l'impression et le téléchargement.
- `api/download_pdf.php` capture le rendu de `print/index.php` et le convertit en PDF A4 avec Dompdf.

## Déploiement

Chaque `push` sur la branche **`master`** déclenche le workflow `.github/workflows/deploy.yml` :

1. Récupération du code.
2. Installation de `lftp`.
3. Synchronisation SFTP (`mirror --reverse`) vers le dossier `cv-web/` du serveur IONOS.

Exclus du transfert : `.git/`, `.github/`, `*.scss`, `*.map`, `README.md`, `.gitignore`.

Secrets GitHub requis : `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`.

> Le workflow n'exécute ni `composer install` ni compilation Sass : `vendor/` et les CSS compilés doivent être présents dans le dépôt.
> Avant un déploiement, vérifier que `RewriteBase /` est bien la ligne active du `.htaccess`.

## Guides rapides

### Ajouter une création

1. Ajouter l'entrée dans `registre.json` → `projects` (`id`, `category`, `year`, `tags`, `image`, `githubUrl`, `demoUrl`).
2. Ajouter les textes (`title`, `shortDescription`, `longDescription`) dans `cv_fr.json` **et** `cv_en.json` → `pages.creations.projects.<id>`.
3. Modèle prêt à copier : `modele-project-data.json`.

### Ajouter une page

1. Déclarer les slugs dans `registre.json` → `meta.slugs`.
2. Déclarer `meta.pages.<clé>` (`name`, `title`, `description`) dans les deux fichiers de langue.
3. Créer `src/controllers/<clé>Controller.php` et `templates/pages/<clé>.php`.
4. Référencer le CSS dans `$pageCssMap` (et le JS dans `$pageJsMap` si besoin) dans `index.php`.
5. Ajouter l'entrée de navigation (`header.nav`) et mettre à jour `sitemap.xml`.

### Ajouter un élément de parcours

Ajouter l'objet dans `registre.json` → `career` (`id`, `category` : `experience` | `etude` | `projet`, dates, lieu, `domaines`, éventuel `linkedGroupId`) puis le texte dans `career_texts.<id>` des deux fichiers de langue.

## Feuille de route

Le détail est suivi dans [`TODO.md`](TODO.md). Prochaines étapes :

- Correction du bug de téléchargement PDF
- Accessibilité : hiérarchie des titres, contrastes (WCAG), suppression du texte justifié
- Vérification du responsive et ajout du mode sombre
- Introduction et transitions de la page Parcours
- Données structurées Schema.org
- Tableau de bord d'administration (statistiques de visite, rapports, logs)

## Versions

Historique détaillé dans [`CHANGELOG.md`](CHANGELOG.md). Version courante : `0.1.0-SNAPSHOT` (développement en cours).

## Licence

Aucune licence open source n'est définie : **tous droits réservés © Julien Tissier**.
