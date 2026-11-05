<?php
declare(strict_types=1);

/**
 * Fichier : database/migrate.php
 * Rôle : Lanceur en ligne de commande des migrations SQL du dashboard (usage local).
 *
 *   php database/migrate.php status [--config=FICHIER]   affiche les migrations appliquées et en attente
 *   php database/migrate.php up     [--config=FICHIER]   applique les migrations en attente
 *
 * La configuration est lue comme pour le site (DashboardConfig) : dashboard.config.php à la racine du projet,
 * ou le fichier donné par --config, ou la variable d'environnement JT_DASHBOARD_CONFIG.
 * En production, on importe les fichiers de database/migrations/ dans phpMyAdmin, dans l'ordre.
 *
 * Code de retour : 0 réussite, 1 erreur, 2 mauvais usage.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../classes/dashboard/DashboardConfig.php';
require_once __DIR__ . '/../classes/dashboard/DashboardDatabase.php';
require_once __DIR__ . '/../classes/dashboard/DashboardMigrator.php';

$command = null;
$configPath = null;

foreach (array_slice($argv, 1) as $argument) {
    if ($argument === 'status' || $argument === 'up') {
        if ($command !== null) {
            $command = '';
            break;
        }
        $command = $argument;
    } elseif (strncmp($argument, '--config=', 9) === 0 && strlen($argument) > 9) {
        $configPath = substr($argument, 9);
    } else {
        $command = '';
        break;
    }
}

if ($command === null || $command === '') {
    fwrite(STDERR, "Usage : php database/migrate.php status|up [--config=FICHIER]\n");
    exit(2);
}

try {
    $config = DashboardConfig::load($configPath);
    $pdo = DashboardDatabase::connect($config, false);
    $migrator = new DashboardMigrator(__DIR__ . '/migrations');

    echo sprintf("Base : %s sur %s:%d\n", $config['db_name'], $config['db_host'], $config['db_port']);

    if ($command === 'status') {
        $applied = $migrator->appliedVersions($pdo);
        $pending = $migrator->pending($pdo);

        echo 'Appliquées : ' . ($applied === [] ? 'aucune' : implode(', ', $applied)) . "\n";
        echo 'En attente : ' . ($pending === [] ? 'aucune' : implode(', ', array_column($pending, 'name'))) . "\n";
        exit(0);
    }

    $done = $migrator->migrate($pdo, static function (string $line): void {
        echo $line . "\n";
    });

    echo $done === [] ? "Rien à faire : la base est à jour.\n" : "Terminé : " . count($done) . " migration(s) appliquée(s).\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
    exit(1);
}
