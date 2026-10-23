# ToDo du CV

Priorités : P0 sécurité/prod · P1 bug visible · P2 dette/amélioration
Statuts : [ ] à faire · [x] fait

## Lot A — Sécurité, infra & routeur (P0)
+ [ ] A1 Liste blanche `profil`/`template`/`lang` (print/index.php, api/download_pdf.php) — faille d'inclusion locale
+ [ ] A2 Interdire l'accès HTTP à data/, src/, classes/, templates/, vendor/ et aux .md/.json/.lock
      + [x] règles .htaccess + test local (30/30)
      + [ ] A2 Test lunch in gitbash from `Documents/Developpement/Projects/core.sh` cmd : `bash check-a2.sh https://www.jtissier-developpeur-informatique.fr`
      + [ ] inventaire SFTP de cv-web/ : supprimer deploy.lftp et les orphelins (info.php, index_2.php, header_2.php, anciens fichiers)
      + [ ] changer le mot de passe SFTP + mettre à jour le secret FTP_PASSWORD (urgent, indépendant du reste)
+ [ ] A3 Contact : erreur générique (log serveur), honeypot + CSRF, liste blanche du motif, champs repeuplés, PRG
+ [ ] A4 .htaccess : retirer RewriteBase, HTTPS + www, en-têtes de sécurité
+ [ ] A5 `<base href>` depuis site_url (plus HTTP_HOST) ; session PHP seulement sur la page contact
+ [ ] A6 Dompdf : isRemoteEnabled = false
+ [ ] A7 deploy.yml : exclusions (TODO, CHANGELOG, composer.*), clé d'hôte épinglée, --delete, concurrency, permissions, test de fumée
+ [ ] A8 Routeur : vraie 404 (code HTTP + 404Controller), 301 vers le slug canonique par langue, params en tableau sans 500

## Lot B — Données & i18n (P1)
+ [ ] B1 contactUsController : clé `forms_errors` (et non `forms_error`)
+ [ ] B2 cv_en.json : titres Intro/presentation, messages_labels (FR+EN), 1re phrase de Intro.texts
+ [ ] B3 Textes manquants pour `Math-Sup` (FR+EN)
+ [ ] B4 Compétences : forme du JSON `skills` + `label` des compétences non traduites ; casse de skills_ids
+ [ ] B5 Chaînes en dur → JSON : creation-detail, "(optionnel)", Oral/Écrit, bouton PDF, alert() de print.js, ternaires $lang
+ [ ] B6 Corriger "portefolio", "Mes Creations", "adress" (redirection si slug modifié)
+ [ ] B7 DataRepository : chargement unique, interpolation jusqu'à stabilité, cache (getCleanUrl)
+ [ ] B8 Source unique des slugs + sitemap généré (x-default, fiches projet)
+ [ ] B9 Mentions légales : adresse hébergeur, SIRET/statut, adresse et téléphone publics (vérif. juridique)

## Lot C — Bugs vues & SCSS (P1)
+ [ ] C1 contactUs.php : ` ?>` parasites, `??` mal placé
+ [ ] C2 head.php : `??` mal placé (×4)
+ [ ] C3 Parcours : "()" vide, années identiques, `$data` écrasé, modificateurs timeline, styles des lignes groupées
+ [ ] C4 legals : classes `legal-*` vs SCSS `legals-*`, variables CSS inexistantes
+ [ ] C5 `--font-primary`, classe `page` sur <body>, import de _color.scss
+ [ ] C6 Créations : <main>, filtres (masquer ou implémenter), GIF → vidéo
+ [ ] C7 Page impression : profil présélectionné
+ [ ] C8 404.php et action du formulaire via getCleanUrl

## Lot D — CI & outillage (P2)
+ [ ] D1 Job qualité avant déploiement : php -l, JSON, compilation SCSS
+ [ ] D2 Script de cohérence des données (bash + jq)
+ [ ] D3 composer.json : php >=8.1 + autoload PSR-4 ; /vendor/ dans .gitignore + composer install en CI
+ [ ] D4 .gitignore complet, structure.md à jour, CHANGELOG au format Keep a Changelog

## Lot E — Design system & accessibilité (P2)
+ [ ] E1 Passage des couleurs en dur (hex) vers les variables CSS
+ [ ] E2 Mode sombre
+ [ ] E3 Contraste WCAG (.card-year, .timeline-item__date…)
+ [ ] E4 Responsive (header sticky, navigation mobile)
+ [ ] E5 Hiérarchie des titres h1-h6 (parcours, home, créations)
+ [ ] E6 Supprimer les survols inutiles
+ [ ] E7 Supprimer `text-align: justify` (home.scss, legals.scss)

## Lot F — PDF (P1)
+ [ ] F1 Corriger le download bug : CSS dédié Dompdf (pas de flexbox) ou abandon au profit de l'impression navigateur
+ [ ] F2 Revoir `overflow:hidden; height:297mm` en @media print (troncature silencieuse)

## Lot G — Dashboard (à arbitrer)
+ [ ] G0 Décision : développement maison ou Matomo/Umami / logs serveur
+ [ ] G1 Si maison : espace de connexion, base de données, page admin, validation
      Fonctions : compteur de visiteurs (robots et développeur exclus), durée de visite, parcours, origine, rapports automatiques réglables, rapports d'erreurs/logs

## Lot H — Contenu & SEO (P2)
+ [ ] H1 Intro de la page parcours
+ [ ] H2 Transitions dans le parcours
+ [ ] H3 Schema.org JSON-LD, og:url/image, twitter:card, hreflang
+ [ ] H4 Corriger le défaut du QR code (Inkscape)

## Lot I — Composants badges (P2) — après B4 (compétences) et E1 (variables CSS)
+ [ ] I1 Classes `Badge` (variantes) et `BadgeGroup` (conteneur), sur le modèle de Button.php
+ [ ] I2 `_badge.scss` : un seul système (variables CSS) en remplacement de `.timeline-item__badge`, `.badge-category`, `.tag`, `.home-skills__item`
+ [ ] I3 Parcours : badge de type + compétences issues de `skills_ids`
+ [ ] I4 Créations : catégorie + tags (grille et fiche projet)
+ [ ] I5 Accueil : bloc dédié "carte infographique" sobre (compétences groupées par type, alimentées par le registre), en remplacement de la liste `home.skills` codée dans le JSON
+ [ ] I6 CV imprimé : styles de badges séparés (Dompdf ne gère pas les variables CSS)

## Fait
+ [x] Labels contact, labels skills, centres d'intérêt, mise en forme du texte home
+ [x] Header, print styles, téléchargement (finalisé, bug restant → F1)
+ [x] Pages légales, politique de confidentialité, CGU/CGV, contact
+ [x] Sécurité d'accès direct (APP_STARTED) sur les contrôleurs
+ [x] Langue du formulaire de contact et du module d'impression
+ [x] Pages créations
