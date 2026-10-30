# Dashboard — base de données (G2)

Statut : livré, à valider · 30/10/2026 · lot G du `TODO.md`
Règles de vie privée : `docs/dashboard-vie-privee.md` (elles priment sur tout ce qui suit).

## 1. Ce que contient G2

| Élément | Chemin | Rôle |
|---|---|---|
| Migrations SQL | `database/migrations/001_core.sql`, `002_access.sql` | Création des tables (MariaDB 11.8 et MySQL 8) |
| Lanceur local | `database/migrate.php` | `php database/migrate.php status` ou `up` |
| Configuration | `classes/dashboard/DashboardConfig.php` | Lit et valide `dashboard.config.php` |
| Connexion | `classes/dashboard/DashboardDatabase.php` | PDO court, délai 2 s, disjoncteur 60 s |
| Migrateur | `classes/dashboard/DashboardMigrator.php` | Découpe, contrôle et applique les migrations |
| Modèle de config | `docs/dashboard.config.example.php` | À copier en `dashboard.config.php` (hors Git) |
| Tests sans SQL | `tests/dashboard/test_dashboard_infra.php` | Config, migrations, schéma, connexion, disjoncteur |
| Test du schéma | `tests/dashboard/test_db_schema.php` | Sur une base locale jetable (MariaDB / MySQL réels) |

Aucune page du site n'utilise encore la base : la collecte arrive avec G4. G2 ne change donc rien au comportement du site.

## 2. Les tables

| Table | Migration | Contenu | Conservation |
|---|---|---|---|
| `schema_migrations` | 001 | versions de migration appliquées | permanente |
| `settings` | 001 | état interne (dernière rotation, dernière purge...) | permanente |
| `visits` | 001 | une ligne par visite : profil, appareil, origine, **clé de visite (pseudonyme, effacée à minuit)** | 13 mois |
| `hits` | 001 | parcours : une ligne par page vue rattachée à une visite (supprimée avec la visite) | 13 mois |
| `daily_stats` | 001 | compteur de **toutes** les pages vues : jour, profil, page, langue, code HTTP | permanente (anonyme) |
| `errors` | 001 | erreurs regroupées : une ligne par jour, type et sujet (un scanner = une ligne) | 90 jours |
| `guided_links` | 002 | visites guidées : libellé, **hachage** du jeton, expiration, révocation | expiration + 30 jours |
| `login_attempts` | 002 | tentatives de connexion à l'admin, clé du jour effacée à minuit | 30 jours |

La table `reports` (rapports automatiques) sera créée par la migration de G10, quand ses réglages seront connus.

### Choix de conception

- **`daily_stats` compte toutes les pages, `hits` ne sert qu'aux parcours.** Les compteurs ne dépendent donc pas de la purge du détail, et un visiteur `DNT` est compté sans visite ni parcours.
- **Jeux de caractères** : tables en `utf8mb4_unicode_ci` (connue de phpMyAdmin 4.9, contrairement à la collation par défaut de MariaDB 11.8). Clés, pages, langues, domaines : colonnes `ascii_bin` (index petits, comparaison exacte).
- **Mode strict** à chaque connexion : une valeur invalide (profil inconnu, texte trop long, caractère hors ASCII dans une colonne ASCII) est refusée, jamais tronquée en silence. Le code qui écrit (G4) doit donc nettoyer ses textes.
- **Dates** en UTC (`DATETIME`, fuseau de session `+00:00`), sauf `visit_day`, `stat_date` et `error_day` : jour Europe/Paris, qui définit la rotation de minuit.
- **Les libellés d'`ENUM` ne sont jamais des nombres** (`not_found` et non `404`) : un `404` entier serait lu comme un numéro de position.
- **Aucune colonne ne peut recevoir d'adresse IP** : le test `test_dashboard_infra.php` échoue si une migration ajoute une colonne `ip`, `user_agent`, `referer`, `email`, `country`, etc.

### Limite de 18 connexions

IONOS limite l'utilisateur de la base à 18 connexions simultanées. `DashboardDatabase` ouvre une connexion courte par requête HTTP, jamais persistante, avec un délai de 2 s. Si la base est saturée, injoignable ou mal configurée, `connectQuietly()` retourne `null` (la page s'affiche normalement), journalise **un code d'erreur sans aucune donnée** et suspend la collecte 60 s (fichier dans le dossier temporaire). Sous MariaDB, chaque requête est en plus limitée à 3 s (`max_statement_time`).

## 3. Mise en place locale (Laragon)

1. **Créer la base de développement** (une base distincte de celle de la production) :
   - interface : Laragon, menu *Base de données*, puis HeidiSQL ou phpMyAdmin : créer `jt_dashboard`, jeu de caractères `utf8mb4`, collation `utf8mb4_unicode_ci` ;
   - ou, dans le terminal de Laragon : `mysql -u root -e "CREATE DATABASE jt_dashboard CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"`.
2. **Créer la configuration** : copier `docs/dashboard.config.example.php` à la racine du projet sous le nom `dashboard.config.php`, puis renseigner `db_user` (`root`), `db_password` (vide par défaut sous Laragon) et un **secret** généré par `openssl rand -hex 32` (Git Bash).
3. **Ajouter `/dashboard.config.php` au `.gitignore`**, puis vérifier avec `git status` que le fichier n'apparaît pas.
4. **Appliquer les migrations** : `php database/migrate.php up`. Vérifier avec `php database/migrate.php status` (« Appliquées : 001, 002 »).
5. **Lancer les tests**, depuis la racine du projet :
   - `php tests/dashboard/test_dashboard_infra.php` : doit afficher `0 échec(s)` ;
   - `php tests/dashboard/test_db_schema.php` : crée une base jetable `jt_dashboard_test_xxxxxxxx`, la contrôle, la supprime ; refuse de tourner ailleurs que sur `localhost` ou `127.0.0.1` ; nécessite un utilisateur autorisé à créer des bases (`root` sous Laragon).
6. Envoyer la **sortie complète** de `test_db_schema.php` (elle indique la version du serveur).

## 4. Mise en production (IONOS)

L'ordre compte : la configuration doit être protégée avant d'être déposée.

1. **Appliquer les modifications du dépôt** (`.htaccess`, `deploy.yml`, `.gitignore`, voir la livraison) et faire passer le contrôle `check-g2.sh local` en local.
2. **Pousser sur `master`**, ou lancer le workflow à la main en **simulation** : le plan ne doit supprimer aucun fichier inattendu (le garde-fou est à 30).
3. **Créer les tables** : phpMyAdmin (espace client IONOS), sélectionner la base dans l'arborescence de gauche, onglet *Importer*, choisir `database/migrations/001_core.sql`, format *SQL*, jeu de caractères *utf-8*, *Exécuter* ; puis le même geste pour `002_access.sql`. Contrôle : la table `schema_migrations` contient les lignes `001` et `002`, et la base compte 8 tables.
4. **Déposer la configuration** : créer `dashboard.config.php` (modèle `docs/dashboard.config.example.php`) avec les valeurs de l'espace IONOS et un secret **propre à la production**, puis l'envoyer par SFTP dans le dossier du site (`cv-web/`, à côté de `index.php`).
5. **Vérifier qu'un déploiement ne l'efface pas** : relancer le workflow en simulation, le fichier `dashboard.config.php` ne doit pas figurer parmi les lignes `rm`.
6. `./check-g2.sh prod` : le fichier de configuration et les dossiers `database/`, `tests/`, `docs/` répondent 403.

Rien dans le site n'ouvre encore la connexion : la première connexion de production aura lieu avec G4, et un échec y sera journalisé sans affecter les pages.

## 5. Écrire une nouvelle migration

- Fichier `database/migrations/NNN_nom_en_minuscules.sql`, numéro suivant sans trou. Les fichiers existants ne se modifient jamais une fois appliqués en production : on ajoute une migration.
- UTF-8 **sans BOM**, instructions terminées par `;`, commentaires `-- ` sur leur propre ligne et sans `;`, aucun `DELIMITER`, aucune procédure, aucun déclencheur, aucun `/*! ... */` (phpMyAdmin 4.9).
- `CREATE TABLE IF NOT EXISTS`, `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`, heures par `UTC_TIMESTAMP()` (jamais `DEFAULT CURRENT_TIMESTAMP`).
- **Dernière instruction** : `INSERT INTO schema_migrations (version, description, applied_at) VALUES ('NNN', '...', UTC_TIMESTAMP());` (description en ASCII).
- `test_dashboard_infra.php` contrôle tout cela et met à jour ses attendus : ajouter les nouvelles tables à la liste du test.
- MySQL et MariaDB n'annulent pas un `CREATE` ou un `ALTER` : si une migration s'arrête à mi-chemin, le message indique l'instruction fautive, et la base se corrige à la main avant de relancer.

## 6. Ce qui n'a pas pu être vérifié par Claude

- L'environnement de Claude n'a pas de serveur MariaDB ni MySQL : le SQL des migrations est contrôlé statiquement (découpage, types, longueur des index, clés étrangères, absence de construction risquée), mais **n'a été exécuté sur aucune vraie base**. `test_db_schema.php` est fait pour cela, sur ton poste, et c'est sa sortie qui valide G2.
- Le comportement de phpMyAdmin 4.9.11 avec MariaDB 11.8 (importation des deux fichiers) se constate à l'étape 3 de la mise en production.
