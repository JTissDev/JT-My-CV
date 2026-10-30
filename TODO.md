# ToDo du CV

Priorités : P0 sécurité/prod · P1 bug visible · P2 dette/amélioration
Statuts : [ ] à faire · [x] fait

## Lot A — Sécurité, infra & routeur (P0)
+ [x] A1 Liste blanche `profil`/`template`/`lang` (print/index.php, api/download_pdf.php) — faille d'inclusion locale
+ [x] A2 Interdire l'accès HTTP à data/, src/, classes/, templates/, vendor/ et aux .md/.json/.lock
      + [x] règles .htaccess + test local (30/30)
      + [x] A2 Test lunch in gitbash from `Documents/Developpement/Projects/core.sh` cmd : `bash check-a2.sh https://www.jtissier-developpeur-informatique.fr`
      + [x] inventaire SFTP de cv-web/ : supprimer deploy.lftp et les orphelins (info.php, index_2.php, header_2.php, anciens fichiers)
      + [x] changer le mot de passe SFTP + mettre à jour le secret FTP_PASSWORD (urgent, indépendant du reste)
+ [x] A3 Contact : erreur générique (log serveur), honeypot + CSRF, liste blanche du motif, champs repeuplés, PRG
+ [x] A4 … htaccess
    + [x] test local : bash check-a4.sh local http://localhost/JT-Developpeur (14 OK)
    + [x] test prod après déploiement : bash check-a4.sh prod (19 OK)
    + [ ] dans quelques jours : HSTS max-age=3600 -> 31536000
+ [x] A5 `<base href>` depuis site_url (plus HTTP_HOST) ; session PHP seulement sur la page contact
      + [x] <base href> en chemin seul (plus d’hôte)
      + [x] session PHP seulement sur la page contact
      + [x] CSP base-uri 'self'
+ [x] A6 Dompdf : isRemoteEnabled = false
+ [x] A7 deploy.yml : exclusions (TODO, CHANGELOG, composer.*), clé d'hôte épinglée, --delete, concurrency, permissions, test de fumée
+ [x] A8 Routeur : vraie 404 (code HTTP + 404Controller), 301 vers le slug canonique par langue, params en tableau sans 500
      + [x] A8a — socle : getCleanUrl branché sur la même source que <base href> (lecture du fichier de langue une seule fois), et correction du </head> tronqué que j’ai livré en A5. Aucun changement visible, 3 fichiers.
      + [x] A8b — vraie 404 et paramètres piégés : code HTTP 404 avec la page du site, plus aucune erreur 500 sur ?page[]=, ?lang[]=, ?creation[]=.
      + [x] A8c — redirections 301 vers le slug canonique par langue, plus correction du sitemap.

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

## Lot G — Dashboard maison (décidé : MariaDB IONOS, collecte sans cookie visiteur, aucune IP stockée)

Principe : on mesure l'audience du site pour moi seul, sans suivi multi-sites ni croisement avec d'autres données. La collecte n'est mise en ligne qu'une fois la politique de confidentialité à jour (G3).
Profils de visite enregistrés à l'écriture (rien n'est jeté, c'est l'affichage qui filtre) : humain · robot · test (JTissier-CheckBot) · développeur · visite guidée.
Ordre : G0 → G1 → G2 → G3 → G4 (collecte en prod, l'historique ne se rattrape pas) → G5 → G6 → G7 → G8 → G9 → G10 → G11.

+ [ ] G0 Prérequis IONOS : base MariaDB standard 2 Go incluse, 18 connexions simultanées max par utilisateur (connexions courtes, jamais persistantes) · reste à faire par moi : créer la base et son utilisateur, noter la version exacte de MariaDB, vérifier la limite de cron jobs (pour G10)
+ [ ] G1 Cahier des charges vie privée : aucune IP écrite en base ni dans le journal applicatif · identifiant de visite = HMAC(sel du jour, IP + User-Agent), tronqué · sel aléatoire supprimé après 24 h (les visites ne sont alors plus rattachables à une IP) · visite terminée après 30 min d'inactivité · Referer réduit au domaine + paramètres utm_* · visites détaillées purgées après 13 mois (CNIL : 25 mois au plus), agrégats conservés
+ [ ] G2 Base de données : schéma (visits, hits, daily_salts, errors, login_attempts, guided_links, settings, reports) en utf8mb4 · migrations SQL versionnées (database/migrations/) · accès PDO, une connexion courte par requête, délai de connexion ~2 s, abandon silencieux si les 18 connexions sont prises · identifiants dans un fichier de config hors Git, créé à la main sur le serveur, exclu du déploiement (deploy.yml) et vérifié inaccessible en HTTP (test de fumée) · base locale Laragon distincte : aucune visite de développement en prod
+ [ ] G3 Politique de confidentialité FR + EN mise à jour (finalité, données collectées, sel quotidien, durées de conservation, journaux de l'hébergeur, cookie de visite guidée) — prérequis de la mise en ligne de G4
+ [ ] G4 Collecte invisible : un seul appel dans index.php (page, langue, code HTTP 200/301/404/500) · classification humain / robot / test / développeur / guidé · fichiers statiques ignorés · ne casse jamais une page (échec silencieux et journalisé, y compris base indisponible ou saturée) · limitation des rafales de 404 (scanners) pour ne pas saturer les 18 connexions
+ [ ] G5 Espace de connexion : mot de passe haché (argon2id si disponible, sinon bcrypt) · session durcie (Secure, HttpOnly, SameSite=Strict, identifiant régénéré) · limitation des tentatives · CSRF · déconnexion · noindex, hors sitemap · cookie « développeur » posé à la connexion (mes visites sont exclues)
+ [ ] G6 Visite guidée, deux modes :
      · Mode appareil : bouton dans l'admin (libellé, durée, ex. 2 h) · les visites de mon appareil sont étiquetées « guidée » + libellé jusqu'à l'expiration
      · Mode lien : lien personnel à jeton (aléatoire, stocké haché, libellé, expiration, révocable depuis l'admin) · traité avant le routeur : pose un cookie de session « guidée » puis redirige en 302 vers l'adresse propre, le jeton ne reste donc ni dans l'URL ni dans l'historique · les aperçus de lien (WhatsApp, Slack, mail…) sont des robots : ils ne valident ni ne consomment le lien
      · Dans les deux cas : visites comptées à part, parcours complet consultable par libellé
+ [ ] G7 Page admin : visiteurs par jour / semaine / mois, profils séparés et filtrables (robots, tests, développeur et visites guidées hors du total « humains ») · origine des visiteurs · pages les plus vues
+ [ ] G8 Durée de visite et parcours (entrée → pages → sortie) · option : balise JS sendBeacon pour la durée de la dernière page
+ [ ] G9 Rapports d'erreurs et de logs : 404 les plus fréquentes, 500, erreurs PHP, tentatives de connexion échouées, scanners · purge automatique
+ [ ] G10 Rapports automatiques réglables : fréquence, contenu, destinataire · cron IONOS appelant un script protégé · envoi par mail
+ [ ] G11 Validation du dashboard : sauvegarde et restauration de la base, purge testée, scripts de test sous le User-Agent robot, revue de sécurité


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
