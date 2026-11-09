<?php
declare(strict_types=1);

/**
 * Fichier : tests/dashboard/test_visit_collector.php
 * Rôle : Teste l'écriture de la collecte (classes/dashboard/VisitCollector.php et CollectorHook.php) sur SQLite.
 *
 *   - normalisation de la requête et statut final d'une page : sans aucune base (toujours exécuté) ;
 *   - toute la logique d'écriture (visites, DNT, 404, rotation de minuit, purge, interblocages, série de 400 requêtes
 *     comparée à un modèle) et le point d'entrée (freins, configuration, disjoncteur) : sur SQLite, dont le schéma est
 *     TRADUIT des vraies migrations par collector-test-support.php. Section ignorée (et signalée) si l'extension
 *     pdo_sqlite n'est pas installée.
 *
 * Les mêmes scénarios tournent sur une vraie base MariaDB ou MySQL avec tests/dashboard/test_collector_mysql.php :
 * c'est lui qui valide le comportement du moteur réel (collation, verrous, mode strict).
 *
 * Lancement (depuis la racine du projet) : php tests/dashboard/test_visit_collector.php
 * Aucun fichier du projet n'est modifié : la base de test est un fichier temporaire, supprimé à la fin.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/collector-scenarios.php';

$ok = 0;
$ko = 0;
$skipped = 0;
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

function skipSection(string $reason): void
{
    global $skipped;
    $skipped++;
    echo "IGNORÉ : {$reason}\n";
}

// Une exception imprévue est un échec lisible, pas un « Fatal error » qui masquerait le reste du compte rendu
set_exception_handler(static function (Throwable $e): void {
    global $ok, $ko, $skipped;
    $ko++;
    echo 'ÉCHEC : exception imprévue (' . get_class($e) . ') : ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ")\n";
    echo "\nRésultat : {$ok} OK, {$ko} échec(s), {$skipped} section(s) ignorée(s) (le test s'est arrêté ici : les contrôles suivants n'ont pas tourné)\n";
    exit(1);
});

function removeTree(string $path): void
{
    if (is_dir($path)) {
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                removeTree($path . DIRECTORY_SEPARATOR . $entry);
            }
        }
        @rmdir($path);
    } elseif (file_exists($path)) {
        @unlink($path);
    }
}

// ----------------------------------------------------------------------
// 1. Sans base de données
// ----------------------------------------------------------------------
collectorPureScenarios('check');

// ----------------------------------------------------------------------
// 2. Sur SQLite
// ----------------------------------------------------------------------
if (!extension_loaded('pdo_sqlite')) {
    skipSection('extension pdo_sqlite absente : scénarios d\'écriture non exécutés ici (test_collector_mysql.php les exécute sur MySQL)');
} else {
    $work = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'jt-collector-test-' . bin2hex(random_bytes(4));
    mkdir($work, 0700);
    $database = $work . DIRECTORY_SEPARATOR . 'test.sqlite';

    try {
        $connect = static function (string $class = PDO::class) use ($database): PDO {
            $pdo = new $class('sqlite:' . $database, null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $pdo->exec('PRAGMA foreign_keys = ON');
            $pdo->exec('PRAGMA busy_timeout = 5000');
            $pdo->exec('PRAGMA synchronous = OFF');
            $pdo->exec('PRAGMA journal_mode = MEMORY');

            return $pdo;
        };

        $pdo = $connect();
        $schema = collectorSqliteSchema(__DIR__ . '/../../database/migrations');
        foreach ($schema as $statement) {
            $pdo->exec($statement);
        }

        // --- le schéma de test est aussi strict que MySQL en mode strict
        check('schéma de test : les 8 tables des migrations', count($schema) === 8);

        $rejects = static function (string $sql) use ($pdo): bool {
            try {
                $pdo->exec($sql);
            } catch (PDOException $e) {
                return true;
            }

            return false;
        };
        $visit = static function (string $set) {
            return "INSERT INTO visits (visit_day, profile, started_at, last_hit_at, {$set}";
        };
        $base = "'2026-01-01', 'human', '2026-01-01 00:00:00', '2026-01-01 00:00:00'";

        check('schéma de test : ligne valide acceptée', $rejects("INSERT INTO visits (visit_day, profile, started_at, last_hit_at) VALUES ({$base})") === false);
        $pdo->exec('DELETE FROM visits');
        check('schéma de test : valeur d\'ENUM inconnue refusée', $rejects("INSERT INTO visits (visit_day, profile, started_at, last_hit_at) VALUES ('2026-01-01', 'alien', '2026-01-01 00:00:00', '2026-01-01 00:00:00')"));
        check('schéma de test : texte plus long que la colonne refusé', $rejects($visit("browser) VALUES ({$base}, '" . str_repeat('a', 33) . "')")));
        check('schéma de test : caractère non ASCII refusé dans une colonne ASCII', $rejects($visit("browser) VALUES ({$base}, 'é')")));
        check('schéma de test : date invalide refusée', $rejects("INSERT INTO visits (visit_day, profile, started_at, last_hit_at) VALUES ('hier', 'human', '2026-01-01 00:00:00', '2026-01-01 00:00:00')"));
        check('schéma de test : heure sans les secondes refusée', $rejects("INSERT INTO visits (visit_day, profile, started_at, last_hit_at) VALUES ('2026-01-01', 'human', '2026-01-01 00:00', '2026-01-01 00:00:00')"));
        check('schéma de test : entier non signé négatif refusé', $rejects($visit("hit_count) VALUES ({$base}, -1)")));
        check('schéma de test : NOT NULL respecté', $rejects("INSERT INTO visits (visit_day, profile, started_at, last_hit_at) VALUES (NULL, 'human', '2026-01-01 00:00:00', '2026-01-01 00:00:00')"));
        check('schéma de test : clé étrangère respectée (parcours sans visite refusé)', $rejects("INSERT INTO hits (visit_id, hit_at, page, lang, status) VALUES (999, '2026-01-01 00:00:00', 'home', 'fr', 200)"));

        $errorRow = "INSERT INTO errors (error_day, kind, subject, message, occurrences, first_at, last_at) VALUES ('2026-01-01', 'not_found', '/x', '', 1, '2026-01-01 00:00:00', '2026-01-01 00:00:00')";
        $pdo->exec($errorRow);
        $duplicate = null;
        try {
            $pdo->exec($errorRow);
        } catch (PDOException $e) {
            $duplicate = $e;
        }
        check('schéma de test : clé unique des erreurs, doublon = SQLSTATE 23000', $duplicate !== null && (string) $duplicate->getCode() === '23000');

        $pdo->exec("INSERT INTO visits (visit_day, profile, started_at, last_hit_at) VALUES ({$base})");
        $pdo->exec("INSERT INTO hits (visit_id, hit_at, page, lang, status) VALUES (" . (int) $pdo->lastInsertId() . ", '2026-01-01 00:00:00', 'home', 'fr', 200)");
        $pdo->exec('DELETE FROM visits');
        check('schéma de test : supprimer une visite supprime son parcours (cascade)', (int) $pdo->query('SELECT COUNT(*) FROM hits')->fetchColumn() === 0);
        cs_reset($pdo);

        // --- les scénarios
        collectorDbScenarios($connect, 'check');
        collectorHookScenarios($connect, 'check', $work);
    } finally {
        removeTree($work);
    }
}

echo "\nRésultat : {$ok} OK, {$ko} échec(s)" . ($skipped > 0 ? ", {$skipped} section(s) ignorée(s)" : '') . "\n";
exit($ko === 0 ? 0 : 1);
