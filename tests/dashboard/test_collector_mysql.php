<?php
declare(strict_types=1);

/**
 * Fichier : tests/dashboard/test_collector_mysql.php
 * Rôle : Exécute les scénarios de la collecte (collector-scenarios.php) sur une VRAIE base MariaDB ou MySQL (celle de
 * Laragon), puis ajoute ce que seul un vrai moteur peut valider : collation, mode strict, caractères sur 4 octets,
 * et plusieurs processus qui écrivent en même temps (création simultanée des mêmes lignes, interblocages).
 *
 * Comme test_db_schema.php, il crée une base jetable « jt_dashboard_test_xxxxxxxx », y applique les migrations, puis
 * la SUPPRIME. Il ne touche jamais à la base « jt_dashboard » de développement.
 *
 * Prérequis : dashboard.config.php (ou --config=FICHIER) avec un utilisateur autorisé à créer des bases.
 * Sous Laragon : « root », mot de passe vide. Refuse de s'exécuter si db_host n'est pas localhost ou 127.0.0.1.
 *
 * Lancement (depuis la racine du projet) : php tests/dashboard/test_collector_mysql.php [--config=FICHIER]
 * Code de retour : 0 tout est bon, 1 au moins un échec, 2 impossible de démarrer.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/collector-scenarios.php';
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

/**
 * Connexion identique à celle du site (DashboardDatabase::connect), éventuellement d'une sous-classe de PDO pour les
 * scénarios qui simulent une panne. Les réglages de session sont les mêmes ; le test vérifie qu'ils sont bien actifs.
 */
function collectorMysqlConnect(array $config, string $class): PDO
{
    if ($class === PDO::class) {
        return DashboardDatabase::connect($config, true);
    }

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $config['db_host'], $config['db_port'], $config['db_name']);
    $pdo = new $class($dsn, $config['db_user'], $config['db_password'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_PERSISTENT         => false,
    ]);
    $pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec("SET time_zone = '+00:00', sql_mode = 'STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION'");

    return $pdo;
}

$configPath = null;
foreach (array_slice($argv, 1) as $argument) {
    if (strncmp($argument, '--config=', 9) === 0 && strlen($argument) > 9) {
        $configPath = substr($argument, 9);
    } else {
        fwrite(STDERR, "Usage : php tests/dashboard/test_collector_mysql.php [--config=FICHIER]\n");
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
$work = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'jt-collector-mysql-' . bin2hex(random_bytes(4));
mkdir($work, 0700);

try {
    $server->exec('CREATE DATABASE `' . $testDatabase . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $created = true;

    echo 'Serveur : ' . $server->query('SELECT VERSION()')->fetchColumn() . "\n";
    echo "Base jetable : {$testDatabase}\n\n";

    $testConfig = ['db_name' => $testDatabase] + $config;
    $migrator   = new DashboardMigrator(__DIR__ . '/../../database/migrations');
    $migrator->migrate(DashboardDatabase::connect($testConfig, false));

    $connect = static function (string $class = PDO::class) use ($testConfig): PDO {
        return collectorMysqlConnect($testConfig, $class);
    };
    $pdo = $connect();

    // --- la connexion de test est celle du site
    $mode = (string) $pdo->query('SELECT @@session.sql_mode')->fetchColumn();
    check('moteur : mode strict actif', strpos($mode, 'STRICT_ALL_TABLES') !== false);
    check('moteur : fuseau de session en UTC', (string) $pdo->query('SELECT @@session.time_zone')->fetchColumn() === '+00:00');
    $other = $connect(CollectorTestPdo::class);
    check('moteur : la connexion de simulation de panne a les mêmes réglages', (string) $other->query('SELECT @@session.sql_mode')->fetchColumn() === $mode && (string) $other->query('SELECT @@session.time_zone')->fetchColumn() === '+00:00');
    $other = null;

    // --- tous les scénarios, sur le vrai moteur
    collectorDbScenarios($connect, 'check');
    collectorHookScenarios($connect, 'check', $work);

    // ------------------------------------------------------------------
    // Ce que seul un vrai moteur peut valider
    // ------------------------------------------------------------------
    $secret = cs_secret();
    $t0     = cs_time('2026-11-10 10:00:00');
    $rec    = static function (array $o, int $now) use ($pdo, $secret): string {
        return VisitCollector::record($pdo, $secret, cs_request($o), $now);
    };

    // Le mode strict refuse bien une valeur trop longue : la collecte doit donc l'avoir bornée elle-même
    cs_reset($pdo);
    $rejected = false;
    try {
        $pdo->prepare("INSERT INTO errors (error_day, kind, subject, message, occurrences, first_at, last_at) VALUES ('2026-11-10', 'not_found', ?, '', 1, '2026-11-10 10:00:00', '2026-11-10 10:00:00')")
            ->execute([str_repeat('a', 256)]);
    } catch (PDOException $e) {
        $rejected = (int) ($e->errorInfo[1] ?? 0) === 1406;
    }
    check('mode strict : un sujet de 256 caractères est refusé (erreur 1406), la collecte le borne donc à 255', $rejected);

    // Collation insensible à la casse : « /Abc » et « /abc » sont la même ligne, y compris pour la mise à jour d'abord
    cs_reset($pdo);
    $rec(['page' => null, 'status' => 404, 'server' => ['REQUEST_URI' => '/Abc']], $t0);
    $rec(['page' => null, 'status' => 404, 'server' => ['REQUEST_URI' => '/abc']], $t0 + 1);
    check('collation : « /Abc » et « /abc » regroupés dans une seule ligne de 2 occurrences', cs_count($pdo, 'errors') === 1 && cs_value($pdo, 'SELECT occurrences FROM errors') == 2);

    // Caractères sur 4 octets
    cs_reset($pdo);
    $rec(['page' => null, 'status' => 404, 'server' => ['REQUEST_URI' => '/café-😀']], $t0);
    check('utf8mb4 : accent et émoji (4 octets) écrits tels quels', cs_value($pdo, 'SELECT subject FROM errors') === '/café-😀');

    // La date limite d'une visite se compare bien en DATE
    cs_reset($pdo);
    $rec([], $t0);
    check('moteur : visit_day est une vraie DATE', cs_value($pdo, "SELECT COUNT(*) FROM visits WHERE visit_day = '2026-11-10'") == 1);

    // ------------------------------------------------------------------
    // Plusieurs processus qui écrivent en même temps
    // ------------------------------------------------------------------
    cs_reset($pdo);
    $workers = 6;
    $rounds  = 40;
    $worker  = __DIR__ . DIRECTORY_SEPARATOR . 'collector-worker.php';
    $running = [];
    // Seuls les réglages de connexion partent vers les processus (le secret binaire ne passe pas en JSON)
    $workerConfig = array_intersect_key($testConfig, array_flip(['db_host', 'db_port', 'db_name', 'db_user', 'db_password']));

    for ($id = 1; $id <= $workers; $id++) {
        $process = proc_open([PHP_BINARY, $worker], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            check("concurrence : processus {$id} lancé", false);
            continue;
        }
        fwrite($pipes[0], (string) json_encode(['driver' => 'mysql', 'config' => $workerConfig, 'id' => $id, 'count' => $rounds, 'secret_hex' => bin2hex($secret)]));
        fclose($pipes[0]);
        $running[] = [$process, $pipes];
    }

    $total = ['counter_ok' => 0, 'error_ok' => 0, 'failed' => 0, 'codes' => []];
    $silent = true;
    foreach ($running as [$process, $pipes]) {
        $report = json_decode((string) stream_get_contents($pipes[1]), true);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);

        if (!is_array($report) || $exit !== 0) {
            $silent = false;
            echo 'Processus en échec (code ' . $exit . ') : ' . trim(substr($stderr, 0, 300)) . "\n";
            continue;
        }
        foreach (['counter_ok', 'error_ok', 'failed'] as $key) {
            $total[$key] += (int) $report[$key];
        }
        foreach ($report['codes'] as $code => $n) {
            $total['codes'][$code] = ($total['codes'][$code] ?? 0) + (int) $n;
        }
    }

    $attempts = $workers * $rounds * 2;
    check("concurrence : les {$workers} processus ont tous rendu un compte rendu", $silent && $total['counter_ok'] + $total['error_ok'] + $total['failed'] === $attempts);

    $homeHits   = (int) cs_value($pdo, "SELECT COALESCE(SUM(hits), 0) FROM daily_stats WHERE page = 'home'");
    $notFound   = (int) cs_value($pdo, "SELECT COALESCE(SUM(hits), 0) FROM daily_stats WHERE page = '404'");
    $errorCount = (int) cs_value($pdo, "SELECT COALESCE(SUM(occurrences), 0) FROM errors WHERE subject = '/contention'");
    check("concurrence : compteur du jour exact ({$total['counter_ok']} écritures réussies = {$homeHits} passages), aucune perdue ni doublée", $homeHits === $total['counter_ok']);
    check("concurrence : 404 comptées exactement ({$total['error_ok']} réussies = {$notFound} au compteur = {$errorCount} dans les erreurs)", $notFound === $total['error_ok'] && $errorCount === $total['error_ok']);
    check('concurrence : une seule ligne d\'erreur et un seul réglage de rotation et de purge malgré la course', cs_count($pdo, 'errors') === 1 && cs_count($pdo, 'settings') === 2);
    check('concurrence : aucune visite créée par ces requêtes (DNT et 404)', cs_count($pdo, 'visits') === 0);
    check("concurrence : moins de la moitié des écritures abandonnées ({$total['failed']} sur {$attempts})", $total['failed'] * 2 < $attempts);

    echo "\nConcurrence : {$workers} processus x {$rounds} tours = {$attempts} écritures, {$total['failed']} abandonnée(s)";
    echo $total['codes'] === [] ? "\n" : ' (' . json_encode($total['codes']) . ")\n";

    cs_reset($pdo);
} catch (Throwable $e) {
    $ko++;
    echo 'ÉCHEC : exception imprévue (' . get_class($e) . ') : ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ")\n";
} finally {
    if ($created) {
        try {
            $server->exec('DROP DATABASE `' . $testDatabase . '`');
            echo "\nBase jetable supprimée.\n";
        } catch (Throwable $e) {
            echo "\nATTENTION : la base jetable {$testDatabase} n'a pas pu être supprimée : supprimez-la à la main.\n";
        }
    }
    foreach (glob($work . DIRECTORY_SEPARATOR . '*', GLOB_NOSORT) ?: [] as $entry) {
        if (is_dir($entry)) {
            foreach (glob($entry . DIRECTORY_SEPARATOR . '*', GLOB_NOSORT) ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($entry);
        } else {
            @unlink($entry);
        }
    }
    @rmdir($work);
}

echo "\nRésultat : {$ok} OK, {$ko} échec(s)\n";
exit($ko === 0 ? 0 : 1);
