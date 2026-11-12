# Dashboard — mise en ligne de la collecte (G4c, 2e partie)

Statut : procédure livrée le 12/11/2026, à dérouler **une fois**, dans l'ordre. Chaque étape a un résultat attendu : si le résultat diffère, on s'arrête et on lit le paragraphe 8.
Ce document remplace le paragraphe 6 de `docs/dashboard-collecte.md` ; les règles de collecte restent décrites dans ce dernier.

## 1. Principe

- **Sans `dashboard.config.php` sur le serveur, la collecte ne fait rien** : le site répond exactement comme aujourd'hui. On peut donc envoyer le code en production avant tout le reste.
- **Avec ce fichier, la collecte démarre** à la requête suivante. Supprimer ou renommer le fichier l'arrête aussitôt (paragraphe 7).
- Le fichier contient le mot de passe de la base et le secret du serveur : il ne passe **jamais** par Git, ni par une conversation. Il est créé par `database/make-dashboard-config.sh`, hors du projet, puis déposé à la main par SFTP. Le déploiement automatique le conserve (liste `KEEP_ON_SERVER` de `deploy.yml`) et `.htaccess` refuse de le servir (403).

## 2. Avant de commencer

Sur ton poste, depuis la racine du projet, tout doit être vert :

```bash
php tests/dashboard/test_visit_collector.php        # 237 OK (43 + 1 section ignorée sans pdo_sqlite)
php tests/dashboard/test_collector_mysql.php        # 195 OK, 0 échec
../website-checker/sites/cv-web/check-g4.sh local http://localhost/JT-Developpeur   # 30 OK (1 ignoré sans pdo_sqlite)
```

Le code de G4a à G4c (classes, `index.php` avec le branchement de la collecte) doit être sur la branche qui part en production.

## 3. Étape 1 — envoyer le code (sans configuration : aucun effet)

Fusionner la branche `DashBoard` dans `master` et pousser, comme pour G2 et G3. Le test de fumée du déploiement doit passer, dont les lignes `/dashboard.config.php -> 403` et `/classes/dashboard/DashboardConfig.php -> 403`.

## 4. Étape 2 — contrôle « avant »

```bash
../website-checker/sites/cv-web/check-g4.sh prod
```

Attendu : **17 OK, 0 échec**. Note les temps de réponse affichés (ordre de grandeur : quelques dixièmes de seconde) : ils servent de référence pour l'étape 6.
Ce passage ne laisse rien en base : la base n'est pas encore branchée.

## 5. Étape 3 — créer les tables (phpMyAdmin IONOS)

Espace client IONOS → Hébergement → Bases de données (le libellé exact du menu peut varier) → ouvrir **phpMyAdmin** sur la base du dashboard.

Pour chacun des deux fichiers, **dans cet ordre** : `database/migrations/001_core.sql` puis `database/migrations/002_access.sql`. Sur ton poste (ces fichiers ne sont jamais envoyés sur le serveur) :

1. Cliquer sur le nom de la base dans la colonne de gauche, puis sur l'onglet **Importer**.
2. Choisir le fichier. **Jeu de caractères du fichier : utf-8. Format : SQL. Mode de compatibilité SQL : NONE.** Laisser le reste par défaut, puis **Exécuter**.
3. Attendre le message vert « L'importation s'est terminée avec succès ».

**Un fichier ne s'importe qu'une fois.** Le 001 est relançable sans dégât (`CREATE TABLE IF NOT EXISTS`) mais son dernier ordre échouera pour doublon ; le 002 contient un `ALTER TABLE` qui n'est pas relançable. Si un import affiche une erreur : ne rien relancer, copier le message exact et me l'envoyer.

Contrôle (onglet **SQL**) :

```sql
SELECT VERSION();
SHOW TABLES;
SELECT version, description, applied_at FROM schema_migrations ORDER BY version;
```

Attendu : une version MariaDB 11.8 ; **8 tables** (`daily_stats`, `errors`, `guided_links`, `hits`, `login_attempts`, `schema_migrations`, `settings`, `visits`) ; deux lignes, `001` et `002`.

*Repartir de zéro, uniquement avant la première collecte (la base ne contient alors aucune donnée à garder) :*

```sql
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS hits, visits, guided_links, login_attempts, daily_stats, errors, settings, schema_migrations;
SET FOREIGN_KEY_CHECKS = 1;
```

## 6. Étape 4 — créer et déposer la configuration

1. Dans Git Bash, à la racine du projet :

   ```bash
   ./database/make-dashboard-config.sh
   ```

   Le script demande les valeurs de l'espace client IONOS (serveur, port, nom de la base, utilisateur), puis le mot de passe **sans l'afficher**, deux fois. Il génère le secret (jamais affiché), écrit `~/dashboard-prod/dashboard.config.php` **hors du projet**, puis le fait relire par la vraie classe `DashboardConfig`. Il refuse d'écrire dans le projet ou dans un dépôt Git, et de remplacer un fichier existant.

2. Déposer ce fichier par SFTP dans `cv-web/`, **à côté de `index.php`** (même dossier que `.htaccess`). Ne pas le renommer.
3. Vérifier qu'il est bien fermé : `https://www.jtissier-developpeur-informatique.fr/dashboard.config.php` doit répondre **403**.

Le mot de passe de la base se retrouve dans l'espace client IONOS. Le secret, lui, n'existe que dans ce fichier : si le fichier est perdu, on en recrée un avec le script ; la seule conséquence est que les visites en cours ce jour-là sont coupées en deux.

## 7. Étape 5 — contrôle « après », puis première collecte

```bash
../website-checker/sites/cv-web/check-g4.sh prod
```

Attendu : **17 OK, 0 échec**, comme à l'étape 2 : le visiteur ne doit voir aucune différence. Compare les temps de réponse à ceux de l'étape 2 : la collecte ne doit pas allonger sensiblement les pages. Si l'écart dépasse environ 50 ms par page, me le signaler (écriture après l'envoi de la page non disponible chez IONOS, paragraphe 4.6 de `dashboard-collecte.md`).

Dans phpMyAdmin, onglet **SQL** (aucune de ces requêtes ne modifie quoi que ce soit ; la clé de visite n'est volontairement pas affichée) :

```sql
-- 1. Pages vues par jour, profil et code
SELECT stat_date, profile, status, SUM(hits) AS pages_vues
FROM daily_stats GROUP BY stat_date, profile, status ORDER BY stat_date DESC, profile, status;

-- 2. Dernières visites
SELECT visit_id, visit_day, profile, device_type, browser, os, referrer_domain, hit_count, started_at, last_hit_at
FROM visits ORDER BY visit_id DESC LIMIT 20;

-- 3. Dernières pages du parcours
SELECT visit_id, hit_at, page, lang, status FROM hits ORDER BY hit_id DESC LIMIT 20;

-- 4. Dernières erreurs
SELECT error_day, kind, subject, occurrences, last_at FROM errors ORDER BY last_at DESC LIMIT 20;

-- 5. État interne (jour de la dernière rotation et de la dernière purge)
SELECT name, value FROM settings ORDER BY name;
```

**Attendu après UN SEUL passage de `check-g4.sh prod` depuis la création du fichier (un deuxième passage dans la demi-heure double les chiffres et prolonge la même visite) :**

| Requête | Résultat |
|---|---|
| 1 | profil `test` : **200 → 6**, **301 → 1**, **404 → 1** (plus, éventuellement, les lignes des vrais visiteurs et des robots déjà passés) |
| 2 | **1 visite** de profil `test`, `hit_count` 6, appareil `unknown`, navigateur et système vides |
| 3 | les 6 pages de cette visite, la page 404 en haut de la liste (la plus récente) |
| 4 | **aucune ligne dont le sujet commence par `/g4-absent-`** (décision D9 : le robot de test n'écrit pas d'erreur 404). Des lignes de vrais robots (`/wp-login.php`, `/.env`…) sont normales |
| 5 | `last_rotation` et `last_purge` à la date du jour (Europe/Paris) |

Ensuite, **une seule visite réelle** depuis ton téléphone en 4G : elle apparaît dans les requêtes 1 et 2 avec le profil `human`, un appareil `mobile`, un navigateur et un système en familles grossières. Jusqu'à G5 (cookie développeur), **tes propres visites comptent comme des visites humaines** ; je te donnerai la requête pour retirer celles de ces premiers essais le moment venu.

Chaque déploiement ajoute désormais quelques lignes de profil `test` (le test de fumée visite quelques pages sous le nom `deploy-smoke`) : c'est normal, ce profil est exclu des statistiques.

**Le lendemain**, après minuit (Europe/Paris) et au moins une requête sur le site :

```sql
SELECT visit_day, COUNT(*) AS visites, SUM(visit_key IS NOT NULL) AS avec_cle
FROM visits GROUP BY visit_day ORDER BY visit_day DESC;
```

Attendu : `avec_cle` = **0** pour tous les jours passés. Seul le jour courant peut avoir des clés. C'est la preuve que le pseudonyme disparaît bien à minuit.

## 8. Si quelque chose ne va pas

**Arrêt d'urgence** : renommer `cv-web/dashboard.config.php` en `dashboard.config.php.off` (SFTP). La collecte s'arrête à la requête suivante, le site continue comme avant. Les données déjà écrites restent en base.

**Le site répond mal après l'étape 4** : renommer le fichier comme ci-dessus, puis me communiquer le résultat de `check-g4.sh prod`.

**Rien n'apparaît dans les requêtes** : ouvrir `cv-web/error_log` (SFTP, fichier créé par IONOS) et chercher les lignes qui commencent par `dashboard :` (elles ne contiennent jamais de mot de passe ni d'adresse) :

| Ligne | Sens | À faire |
|---|---|---|
| `base de données indisponible (code 1045)` | utilisateur ou mot de passe refusé | refaire le fichier avec le script, redéposer |
| `… (code 1049)` | nom de base inconnu | idem, vérifier le nom dans l'espace client |
| `… (code 1044)` | l'utilisateur n'a pas accès à cette base | vérifier dans l'espace client |
| `… (code 2002)` ou `2006` | serveur ou port injoignable | vérifier le serveur et le port |
| `… (code 1203)` ou `1040` | trop de connexions simultanées (IONOS : 18) | me le signaler avec l'heure |
| `écriture impossible (PDO 42S02/1146)` | table absente | refaire l'étape 3 |
| `configuration inutilisable, collecte arrêtée (…)` | fichier de configuration invalide | refaire le fichier avec le script |
| `fichier manquant, collecte arrêtée (…)` | un fichier de `classes/dashboard/` n'est pas sur le serveur | vérifier le déploiement |

Après une panne de base, la collecte se suspend **60 secondes** puis réessaie d'elle-même ; les pages du site ne sont jamais ralenties ni bloquées par la collecte.

## 9. Limites connues de cette mise en ligne

- **Effacement des clés et purge** : ils s'exécutent à la première requête enregistrée de chaque nouveau jour, pas à heure fixe. Un site sans aucune visite pendant une journée garde les clés de la veille jusqu'à la requête suivante (les robots passent en général plusieurs fois par jour). Un cron IONOS (lot G10) fixera l'heure si l'hébergement le permet.
- **Un seul utilisateur de base** : IONOS ne propose qu'un utilisateur par base, avec tous les droits. Le fichier de configuration est donc le seul rempart : d'où le 403 contrôlé à l'étape 4, et le fichier jamais dans Git.
- Les requêtes de contrôle ont été vérifiées sur la base de test du projet (SQLite). Les migrations ont été exécutées sur MySQL 5.7 ; leur import sur MariaDB 11.8 est précisément ce que l'étape 3 contrôle.
