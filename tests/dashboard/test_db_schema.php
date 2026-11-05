<?php
declare(strict_types=1);

/**
 * Fichier : tests/dashboard/test_db_schema.php
 * Rôle : Vérifie les migrations et le comportement du schéma sur une VRAIE base MariaDB ou MySQL (celle de Laragon).
 *
 * Le test crée une base jetable « jt_dashboard_test_xxxxxxxx », y applique les migrations, contrôle le schéma
 * (jeux de caractères, clés étrangères, mode strict...) et le comportement attendu par la collecte (cascade,
 * mise à NULL, regroupement des erreurs, effacement des clés de visite), puis SUPPRIME cette base.
 * Il ne touche jamais à la base « jt_dashboard » de développement.
 *
 * Prérequis : dashboard.config.php (ou --config=FICHIER) avec un utilisateur autorisé à créer des bases.
 * Sous Laragon, c'est l'utilisateur « root » (mot de passe vide par défaut).
 * Sécurité : refuse de s'exécuter si db_host n'est pas localhost ou 127.0.0.1.
 *
 * Lancement (depuis la racine du projet) : php tests/dashboard/test_db_schema.php [--config=FICHIER]
 * Code de retour : 0 tout est bon, 1 au moins un échec, 2 impossible de démarrer.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../classes/dashboard/DashboardConfig.php';
require_once __DIR__ . '/../../classes/dashboard/DashboardDatabase.php';
require_once __DIR__ . '/../../classes/dashboard/DashboardMigrator.php';

$ok = 0;
$ko = 0;
function check(string $label, bool $cond): void
{
    global $ok, $ko;
    if ($cond) {
        $ok++;
    } else {
        $ko++;
        echo "ÉCHEC : {$label}\n";
    }
}

/** Vrai si l'appel est refusé par la base (PDOException). Si $errno est donné, le code d'erreur du moteur doit correspondre. */
function rejectedBySql(callable $call, ?int $errno = null): bool
{
    try {
        $call();
    } catch (PDOException $e) {
        return $errno === null || (int) ($e->errorInfo[1] ?? 0) === $errno;
    }

    return false;
}

$configPath = null;
foreach (array_slice($argv, 1) as $argument) {
    if (strncmp($argument, '--config=', 9) === 0 && strlen($argument) > 9) {
        $configPath = substr($argument, 9);
    } else {
        fwrite(STDERR, "Usage : php tests/dashboard/test_db_schema.php [--config=FICHIER]\n");
        exit(2);
    }
}

try {
    $config = DashboardConfig::load($configPath);
} catch (RuntimeException $e) {
    fwrite(STDERR, 'Impossible de démarrer : ' . $e->getMessage() . "\n");
    exit(2);
}

if (!in_array($config['db_host'], ['localhost', '127.0.0.1'], true)) {
    fwrite(STDERR, "Refusé : ce test crée et supprime une base, il ne s'exécute que sur localhost ou 127.0.0.1 (db_host = {$config['db_host']}).\n");
    exit(2);
}

// Connexion au serveur (sans base), pour créer la base jetable
try {
    $server = new PDO(
        sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $config['db_host'], $config['db_port']),
        $config['db_user'],
        $config['db_password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    fwrite(STDERR, 'Impossible de se connecter au serveur : ' . $e->getMessage() . "\n");
    exit(2);
}

$testDatabase = 'jt_dashboard_test_' . bin2hex(random_bytes(4));
$created = false;
$scratch = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'jt-db-test-' . bin2hex(random_bytes(4));

try {
    $server->exec('CREATE DATABASE `' . $testDatabase . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $created = true;

    echo 'Serveur : ' . $server->query('SELECT VERSION()')->fetchColumn() . "\n";
    echo "Base jetable : {$testDatabase}\n\n";

    $testConfig = ['db_name' => $testDatabase] + $config;
    $pdo = DashboardDatabase::connect($testConfig, false);
    $migrator = new DashboardMigrator(__DIR__ . '/../../database/migrations');

    // ------------------------------------------------------------------
    // 1. Migrations
    // ------------------------------------------------------------------
    check('migrations : base vierge, 2 en attente', array_column($migrator->pending($pdo), 'version') === ['001', '002']);
    $applied = $migrator->migrate($pdo);
    check('migrations : 001 et 002 appliquées', $applied === ['001', '002']);
    check('migrations : deuxième passage sans effet', $migrator->migrate($pdo) === []);
    check('migrations : versions enregistrées', $pdo->query('SELECT version FROM schema_migrations ORDER BY version')->fetchAll(PDO::FETCH_COLUMN) === ['001', '002']);

    $age = (int) $pdo->query('SELECT TIMESTAMPDIFF(SECOND, MIN(applied_at), UTC_TIMESTAMP()) FROM schema_migrations')->fetchColumn();
    check("migrations : applied_at est en UTC (écart {$age} s)", $age >= -5 && $age < 300);

    // Appliquer deux fois le même fichier : les CREATE TABLE IF NOT EXISTS passent, l'enregistrement final est refusé (doublon)
    $statements = DashboardMigrator::splitStatements((string) file_get_contents(__DIR__ . '/../../database/migrations/001_core.sql'));
    $record = array_pop($statements);
    check('ré-import : les CREATE TABLE IF NOT EXISTS ne détruisent rien', (function () use ($pdo, $statements): bool {
        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }
        return true;
    })());
    check('ré-import : l\'enregistrement final est refusé (doublon 1062), l\'erreur se voit', rejectedBySql(static function () use ($pdo, $record): void {
        $pdo->exec((string) $record);
    }, 1062));

    // ------------------------------------------------------------------
    // 2. Structure
    // ------------------------------------------------------------------
    $tables = $pdo->query(
        'SELECT table_name AS t, engine AS e, table_collation AS c FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY table_name'
    )->fetchAll();
    $names = array_column($tables, 't');
    check('structure : 8 tables', $names === ['daily_stats', 'errors', 'guided_links', 'hits', 'login_attempts', 'schema_migrations', 'settings', 'visits']);
    check('structure : toutes en InnoDB', array_unique(array_column($tables, 'e')) === ['InnoDB']);
    check('structure : toutes en utf8mb4_unicode_ci', array_unique(array_column($tables, 'c')) === ['utf8mb4_unicode_ci']);

    $column = static function (string $table, string $name) use ($pdo): array {
        $statement = $pdo->prepare(
            'SELECT column_type AS type, character_set_name AS charset, collation_name AS collation, is_nullable AS nullable '
            . 'FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
        );
        $statement->execute([$table, $name]);
        $row = $statement->fetch();

        return $row === false ? [] : $row;
    };

    $visitKey = $column('visits', 'visit_key');
    check('visits.visit_key : char(32), ascii_bin, nullable', ($visitKey['type'] ?? '') === 'char(32)' && ($visitKey['collation'] ?? '') === 'ascii_bin' && ($visitKey['nullable'] ?? '') === 'YES');
    check('login_attempts.visit_key : nullable', ($column('login_attempts', 'visit_key')['nullable'] ?? '') === 'YES');
    check('visits.referrer_domain : ascii', ($column('visits', 'referrer_domain')['charset'] ?? '') === 'ascii');
    check('hits.page : ascii_bin', ($column('hits', 'page')['collation'] ?? '') === 'ascii_bin');
    check('guided_links.label : utf8mb4', ($column('guided_links', 'label')['charset'] ?? '') === 'utf8mb4');
    check('visits.profile : les cinq profils', ($column('visits', 'profile')['type'] ?? '') === "enum('human','bot','test','developer','guided')");
    check('daily_stats.profile : mêmes profils que visits', ($column('daily_stats', 'profile')['type'] ?? '') === ($column('visits', 'profile')['type'] ?? 'x'));

    $foreign = $pdo->query(
        'SELECT constraint_name AS name, delete_rule AS rule FROM information_schema.referential_constraints '
        . 'WHERE constraint_schema = DATABASE() ORDER BY constraint_name'
    )->fetchAll();
    check('clés étrangères : fk_hits_visit (CASCADE) et fk_visits_guided (SET NULL)', $foreign === [
        ['name' => 'fk_hits_visit', 'rule' => 'CASCADE'],
        ['name' => 'fk_visits_guided', 'rule' => 'SET NULL'],
    ]);

    // ------------------------------------------------------------------
    // 3. Comportement : données, jeu de caractères, mode strict
    // ------------------------------------------------------------------
    $now = gmdate('Y-m-d H:i:s');
    $later = gmdate('Y-m-d H:i:s', time() + 86400);
    $day = '2026-10-29';
    $yesterday = '2026-10-28';

    $label = 'Entretien Société É — démo 🙂';
    $tokenHash = hash('sha256', 'jeton-de-test');
    $pdo->prepare('INSERT INTO guided_links (mode, label, token_hash, created_at, expires_at) VALUES (?, ?, ?, ?, ?)')
        ->execute(['link', $label, $tokenHash, $now, $later]);
    $guidedId = (int) $pdo->lastInsertId();
    $stored = $pdo->query('SELECT label FROM guided_links WHERE guided_id = ' . $guidedId)->fetchColumn();
    check('utf8mb4 : accents et émoji conservés', $stored === $label);
    check('unicité : un même jeton ne peut être enregistré deux fois (1062)', rejectedBySql(static function () use ($pdo, $now, $later, $tokenHash): void {
        $pdo->prepare('INSERT INTO guided_links (mode, label, token_hash, created_at, expires_at) VALUES (?, ?, ?, ?, ?)')
            ->execute(['device', 'doublon', $tokenHash, $now, $later]);
    }, 1062));

    $insertVisit = static function (string $key, string $visitDay, string $profile, ?int $guided) use ($pdo, $now): int {
        $pdo->prepare('INSERT INTO visits (visit_key, visit_day, profile, guided_id, started_at, last_hit_at) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$key, $visitDay, $profile, $guided, $now, $now]);

        return (int) $pdo->lastInsertId();
    };
    $insertHit = static function (int $visitId, string $page) use ($pdo, $now): void {
        $pdo->prepare('INSERT INTO hits (visit_id, hit_at, page, lang, status) VALUES (?, ?, ?, ?, ?)')->execute([$visitId, $now, $page, 'fr', 200]);
    };

    $keyA = str_repeat('a', 32);
    $keyB = str_repeat('b', 32);
    $visitToday = $insertVisit($keyA, $day, 'guided', $guidedId);
    $visitOld = $insertVisit($keyB, $yesterday, 'human', null);
    foreach (['home', 'career', 'contactUs'] as $page) {
        $insertHit($visitToday, $page);
    }
    $insertHit($visitOld, 'home');

    check('valeurs par défaut : hit_count = 1, device_type = unknown', (function () use ($pdo, $visitToday): bool {
        $row = $pdo->query('SELECT hit_count AS h, device_type AS d FROM visits WHERE visit_id = ' . $visitToday)->fetch();
        return (int) $row['h'] === 1 && $row['d'] === 'unknown';
    })());

    check('clé de visite : la recherche respecte la casse (ascii_bin)', (function () use ($pdo, $keyA): bool {
        $statement = $pdo->prepare('SELECT COUNT(*) FROM visits WHERE visit_key = ?');
        $statement->execute([$keyA]);
        $exact = (int) $statement->fetchColumn();
        $statement->execute([strtoupper($keyA)]);
        return $exact === 1 && (int) $statement->fetchColumn() === 0;
    })());

    check('recherche de la visite en cours (clé + dernière page récente)', (function () use ($pdo, $keyA, $visitToday): bool {
        $statement = $pdo->prepare('SELECT visit_id FROM visits WHERE visit_key = ? AND last_hit_at > ? ORDER BY last_hit_at DESC LIMIT 1');
        $statement->execute([$keyA, gmdate('Y-m-d H:i:s', time() - 1800)]);
        return (int) $statement->fetchColumn() === $visitToday;
    })());

    check('heure : DATETIME relu à l\'identique (pas de conversion de fuseau)', (function () use ($pdo): bool {
        $pdo->exec("SET time_zone = '+00:00'");
        $pdo->prepare('INSERT INTO settings (name, value, updated_at) VALUES (?, ?, ?)')->execute(['probe', 'x', '2026-10-29 23:59:59']);
        return $pdo->query("SELECT updated_at FROM settings WHERE name = 'probe'")->fetchColumn() === '2026-10-29 23:59:59';
    })());

    // Mode strict : une valeur invalide est refusée, jamais tronquée en silence
    check('strict : profil inconnu refusé', rejectedBySql(static function () use ($pdo, $now): void {
        $pdo->prepare('INSERT INTO visits (visit_day, profile, started_at, last_hit_at) VALUES (?, ?, ?, ?)')->execute(['2026-10-29', 'robot', $now, $now]);
    }));
    check('strict : profil NULL refusé', rejectedBySql(static function () use ($pdo, $now): void {
        $pdo->prepare('INSERT INTO visits (visit_day, profile, started_at, last_hit_at) VALUES (?, ?, ?, ?)')->execute(['2026-10-29', null, $now, $now]);
    }));
    check('strict : page de 33 caractères refusée (limite 32)', rejectedBySql(static function () use ($insertHit, $visitToday): void {
        $insertHit($visitToday, str_repeat('p', 33));
    }));
    check('strict : caractère hors ASCII refusé dans une colonne ASCII', rejectedBySql(static function () use ($insertHit, $visitToday): void {
        $insertHit($visitToday, 'accueil-é');
    }));
    check('strict : date invalide refusée', rejectedBySql(static function () use ($pdo, $now): void {
        $pdo->prepare('INSERT INTO visits (visit_day, profile, started_at, last_hit_at) VALUES (?, ?, ?, ?)')->execute(['2026-02-31', 'human', $now, $now]);
    }));
    check('clé étrangère : un hit sans visite est refusé (1452)', rejectedBySql(static function () use ($insertHit): void {
        $insertHit(999999, 'home');
    }, 1452));
    check('clé étrangère : visite guidée inexistante refusée (1452)', rejectedBySql(static function () use ($insertVisit): void {
        $insertVisit(str_repeat('c', 32), '2026-10-29', 'guided', 424242);
    }, 1452));

    // Compteurs quotidiens : une ligne par jour/profil/page/langue/code, incrémentée
    $bump = $pdo->prepare('INSERT INTO daily_stats (stat_date, profile, page, lang, status, hits) VALUES (?, ?, ?, ?, ?, 1) ON DUPLICATE KEY UPDATE hits = hits + 1');
    $bump->execute([$day, 'human', 'home', 'fr', 200]);
    $bump->execute([$day, 'human', 'home', 'fr', 200]);
    $bump->execute([$day, 'bot', 'home', 'fr', 200]);
    check('daily_stats : 2 lignes (humain : 2 pages vues, robot : 1)', (function () use ($pdo): bool {
        $byProfile = [];
        foreach ($pdo->query('SELECT profile AS p, hits AS h FROM daily_stats')->fetchAll() as $row) {
            $byProfile[$row['p']] = (int) $row['h'];
        }
        return $byProfile === ['human' => 2, 'bot' => 1] || $byProfile === ['bot' => 1, 'human' => 2];
    })());

    // Erreurs regroupées
    $error = $pdo->prepare(
        'INSERT INTO errors (error_day, kind, subject, message, first_at, last_at) VALUES (?, ?, ?, ?, ?, ?) '
        . 'ON DUPLICATE KEY UPDATE occurrences = occurrences + 1, last_at = ?'
    );
    $error->execute([$day, 'not_found', '/wp-login.php', '', $now, $now, $now]);
    $error->execute([$day, 'not_found', '/wp-login.php', '', $now, $now, $now]);
    $error->execute([$day, 'not_found', '/WP-LOGIN.PHP', '', $now, $now, $now]);
    $error->execute([$day, 'server_error', '/wp-login.php', 'autre type', $now, $now, $now]);
    $errorsByKind = [];
    foreach ($pdo->query('SELECT kind AS k, occurrences AS n FROM errors')->fetchAll() as $row) {
        $errorsByKind[$row['k']] = (int) $row['n'];
    }
    check('errors : un scanner ne crée qu\'une ligne par jour et par chemin (3 occurrences, casse ignorée)', ($errorsByKind['not_found'] ?? 0) === 3);
    check('errors : un autre type d\'erreur est une autre ligne', ($errorsByKind['server_error'] ?? 0) === 1 && count($errorsByKind) === 2);
    check('errors : sujet de 255 caractères accepté, 256 refusé', (function () use ($pdo, $day, $now): bool {
        $insert = $pdo->prepare('INSERT INTO errors (error_day, kind, subject, first_at, last_at) VALUES (?, ?, ?, ?, ?)');
        $insert->execute([$day, 'php', str_repeat('é', 255), $now, $now]);
        return rejectedBySql(static function () use ($insert, $day, $now): void {
            $insert->execute([$day, 'php', str_repeat('é', 256), $now, $now]);
        });
    })());

    // Tentatives de connexion
    $pdo->prepare('INSERT INTO login_attempts (attempted_at, visit_key, succeeded) VALUES (?, ?, 0)')->execute([$now, $keyB]);
    $pdo->prepare('INSERT INTO login_attempts (attempted_at, visit_key, succeeded) VALUES (?, ?, 1)')->execute([$now, $keyA]);

    // Effacement des clés des jours passés (rotation de minuit)
    $rotateVisits = $pdo->prepare('UPDATE visits SET visit_key = NULL WHERE visit_day < ? AND visit_key IS NOT NULL');
    $rotateVisits->execute([$day]);
    check('rotation : une seule visite perd sa clé (celle d\'hier)', $rotateVisits->rowCount() === 1);
    check('rotation : la visite du jour garde sa clé, celle d\'hier est NULL', (function () use ($pdo, $visitToday, $visitOld, $keyA): bool {
        $today = $pdo->query('SELECT visit_key FROM visits WHERE visit_id = ' . $visitToday)->fetchColumn();
        $old = $pdo->query('SELECT visit_key FROM visits WHERE visit_id = ' . $visitOld)->fetchColumn();
        return $today === $keyA && $old === null;
    })());
    check('rotation : la visite d\'hier et son parcours restent (seule la clé disparaît)', (int) $pdo->query('SELECT COUNT(*) FROM hits WHERE visit_id = ' . $visitOld)->fetchColumn() === 1);
    $pdo->exec("UPDATE login_attempts SET visit_key = NULL WHERE attempted_at < '" . gmdate('Y-m-d H:i:s', time() + 60) . "' AND succeeded = 0");
    check('rotation : tentatives de connexion, clé effacée', (int) $pdo->query('SELECT COUNT(*) FROM login_attempts WHERE visit_key IS NULL')->fetchColumn() === 1);

    // Cascade et mise à NULL
    check('parcours : 3 pages enregistrées pour la visite du jour (aucune des insertions refusées n\'a laissé de trace)', (int) $pdo->query('SELECT COUNT(*) FROM hits WHERE visit_id = ' . $visitToday)->fetchColumn() === 3);
    $pdo->exec('DELETE FROM visits WHERE visit_id = ' . $visitOld);
    check('cascade : supprimer la visite supprime son parcours', (int) $pdo->query('SELECT COUNT(*) FROM hits WHERE visit_id = ' . $visitOld)->fetchColumn() === 0);

    $pdo->exec('DELETE FROM guided_links WHERE guided_id = ' . $guidedId);
    check('SET NULL : supprimer le lien guidé garde la visite, sans libellé', (function () use ($pdo, $visitToday): bool {
        $row = $pdo->query('SELECT guided_id AS g FROM visits WHERE visit_id = ' . $visitToday)->fetch();
        return $row !== false && $row['g'] === null;
    })());

    // ------------------------------------------------------------------
    // 4. Connexion « pages du site » (DashboardDatabase::connectQuietly) sur cette base
    // ------------------------------------------------------------------
    mkdir($scratch, 0700, true);
    $quiet = DashboardDatabase::connectQuietly($testConfig, $scratch . DIRECTORY_SEPARATOR . 'cooldown');
    check('connexion silencieuse : réussit sur une base valide', $quiet instanceof PDO);
    if ($quiet instanceof PDO) {
        $session = $quiet->query('SELECT @@session.time_zone AS tz, @@session.sql_mode AS mode, @@collation_connection AS coll, @@character_set_client AS cs')->fetch();
        check('session : fuseau +00:00', $session['tz'] === '+00:00');
        check('session : mode strict', strpos((string) $session['mode'], 'STRICT_ALL_TABLES') !== false);
        check('session : utf8mb4_unicode_ci', $session['coll'] === 'utf8mb4_unicode_ci' && $session['cs'] === 'utf8mb4');
        check('session : pas de connexion persistante', $quiet->getAttribute(PDO::ATTR_PERSISTENT) === false);

        $limit = null;
        try {
            $limit = $quiet->query('SELECT @@session.max_statement_time')->fetchColumn();
        } catch (PDOException $e) {
            $limit = null; // MySQL : variable propre à MariaDB
        }
        if ($limit === null) {
            echo "INFO    max_statement_time absent sur ce serveur (normal sous MySQL) : pas de limite de durée par requête ici.\n";
        } else {
            check('MariaDB : max_statement_time = 3 s sur les connexions du site', (float) $limit === 3.0);
        }
        $quiet = null;
    }

    $badPassword = DashboardDatabase::connectQuietly(['db_password' => $config['db_password'] . 'x-mauvais'] + $testConfig, $scratch . DIRECTORY_SEPARATOR . 'cooldown-bad');
    check('connexion silencieuse : mauvais mot de passe -> null, sans exception', $badPassword === null);
} catch (Throwable $e) {
    $ko++;
    echo 'ÉCHEC : exception imprévue (' . get_class($e) . ') : ' . $e->getMessage() . "\n";
} finally {
    if ($created) {
        try {
            $server->exec('DROP DATABASE `' . $testDatabase . '`');
            echo "\nBase jetable supprimée.\n";
        } catch (Throwable $e) {
            echo "\nATTENTION : la base jetable {$testDatabase} n'a pas pu être supprimée : supprimez-la à la main.\n";
        }
    }
    if (is_dir($scratch)) {
        foreach (glob($scratch . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($scratch);
    }
}

echo "\nRésultat : {$ok} OK, {$ko} échec(s)\n";
exit($ko === 0 ? 0 : 1);
