# Changelog

## [0.1.0-SNAPSHOT] - Ongoing Development

### 2026-11-12
* **Added**
> - dashboard (J.Tissier)
>   - Create dashboard

---

### 2026-11-07
* **Added**
> - Projet jt-dev-flow (J.Tissier)
>   - Intégration du projet et mise à jour de la page d'accueil
> - Dashboard G4a (J.Tissier)
>   - Préparation de la structure du tableau de bord

---

* **Changed**
> - SiteMap.xml (J.Tissier)
>   - Mise à jour des dates de modification

---

* **Fixed**
> - Pages légales (J.Tissier)
>   - Correction des styles par défaut et des anomalies visuelles
>   - Intégration des liens vers les profils sociaux (GitHub, LinkedIn, Codeur.com, CodePen)

---

### 2026-11-05
* **Fixed**
> - Lot G3 - Confidentialité (J.Tissier)
>   - Finalisation et validation de la politique de confidentialité

---

### 2026-10-28
* **Fixed**
> - Routeur (J.Tissier)
>   - Mise en place d'une vraie page 404 (code HTTP et contrôleur dédié)
>   - Implémentation des redirections 301 vers les slugs canoniques par langue
>   - Gestion des balises canoniques multilingues

---

### 2026-10-24
* **Fixed**
> - Page Contact (J.Tissier)
>   - Correction de bogues sur le formulaire et la gestion des messages
> - Fichier .htaccess (J.Tissier)
>   - Ajustement des règles de réécriture et de sécurité
> - Configuration (J.Tissier)
>   - Correction du chemin de base (`<base href>`)

---

### 2026-10-23
* **Fixed**
> - Sécurité A1 (J.Tissier)
>   - Correction de la faille d'inclusion locale (liste blanche profils/templates/langues)
> - Chargement des données (J.Tissier)
>   - Correction d'un bogue de surcharge lors du chargement

---

### 2026-10-19
* **Added**
> - Pages de créations (J.Tissier)
>   - Mise en place de la structure logique des projets

---

### 2026-10-18
* **Added**
> - URLs propres (J.Tissier)
>   - Implémentation initiale du système d'URL propre (en cours de finalisation)

---

* **Fixed**
> - Gestion des adresses (J.Tissier)
>   - Correction de la traduction et de l'affichage des adresses

---

### 2026-10-17
* **Added**
> - En-tête du site (J.Tissier)
>   - Intégration du logo dans le header

---

### 2026-10-13
* **Added**
> - Fichiers de configuration SEO & métadonnées (J.Tissier)
>   - Ajout du favicon, du `robots.txt` et du sitemap
> - Module d'impression et de génération PDF (J.Tissier)
>   - Contrôleur A4 dynamique (`index.php`)
>   - Vues et partiels d'impression (`cv_a4_default.php`, `main.php`, `sidebar.php`, `header.php`)
>   - Styles SCSS stricts pour le format A4 (`cv_a4_default.scss`)
>   - Utilitaire de débogage (`debug.php`)

---

* **Fixed**
> - Formulaire de contact (J.Tissier)
>   - Correction du maintien de la langue active après l'envoi d'un message
> - Internationalisation et ergonomie (J.Tissier)
>   - Correction de fautes d'orthographe et des messages du bouton d'envoi

---

### 2026-10-12
* **Added**
> - Pages légales (J.Tissier)
>   - Création des pages de politique de confidentialité, CGU et CGV
> - Module d'impression (J.Tissier)
>   - Synchronisation de la langue dans `changeProfil()` (`scripts/print.js`) pour préserver le paramètre `lang`

---

* **Fixed**
> - Chargement des données (J.Tissier)
>   - Correction d'une anomalie générale de chargement

---

### 2026-10-11
* **Added**
> - Page Contact (J.Tissier)
>   - Implémentation de la vue, du contrôleur et de la structure complète du formulaire

---

* **Fixed**
> - Envoi de courriers et chargement des données (J.Tissier)
>   - Fiabilisation de l'envoi d'e-mails
>   - Chargement dynamique des messages d'erreur des formulaires

---

### 2026-10-10
* **Added**
> - Pages légales (J.Tissier)
>   - Ajout initial des contrôleurs, données, styles et liens des pages légales
> - Section Centres d'intérêt (J.Tissier)
>   - Intégration des centres d'intérêt dans la barre latérale d'impression (`print/sidebar`)

---

* **Fixed**
> - Sécurité et accès (J.Tissier)
>   - Ajustement temporaire de la sécurité d'accès direct
> - Pages légales (J.Tissier)
>   - Correction de l'accès aux pages et de l'affichage des données
> - En-tête et affichage (J.Tissier)
>   - Mise à jour du chargement des données du header et correction des visuels de la page d'impression
> - Typographie (J.Tissier)
>   - Suppression du texte justifié (`text-align: justify`) sur la page d'accueil
> - Centres d'intérêt (J.Tissier)
>   - Correction des libellés de contact et suppression du titre de groupe de compétences redondant

---

* **Changed**
> - Suivi du projet (J.Tissier)
>   - Mise à jour du fichier de tâches (`TODO.md`)

---

### 2026-10-09
* **Changed**
> - Structure des routes (J.Tissier)
>   - Renommage du slug de "Parcours" vers "career"

---

* **Fixed**
> - Traductions (J.Tissier)
>   - Correction des bogues de traduction suite au renommage des routes

---

* **Added**
> - Fonctionnalité de téléchargement (J.Tissier)
>   - Ajout initial de l'option de téléchargement (version préliminaire avec bogues connus)
