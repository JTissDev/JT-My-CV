<?php
declare(strict_types=1);

/**
 * Fichier : tests/dashboard/collector-worker.php
 * Rôle : Processus de test de concurrence (lancé par test_collector_mysql.php, jamais à la main).
 *
 * Lit un travail JSON sur l'entrée standard (jamais en argument : le mot de passe de la base n'apparaîtrait pas dans
 * la liste des processus), enregistre des pages en boucle, puis affiche un compte rendu JSON sur la sortie standard.
 * Plusieurs de ces processus tournent en même temps sur la même base : ils se disputent la création des mêmes lignes
 * (compteur du jour, erreur du jour, réglages de rotation et de purge), ce que seule une vraie base peut arbitrer.
 *
 * Chaque tour enregistre deux pages :
 *   a. une page d'un visiteur qui envoie DNT (compteur seul) ;
 *   b. une 404 sur le même chemin, d'un visiteur propre à ce processus (compteur + erreur regroupée).
 * Une écriture qui échoue est annulée en bloc : le nombre d'écritures réussies doit donc retrouver, au passage près,
 * les compteurs de la base.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../classes/dashboard/VisitCollector.php';
require_once __DIR__ . '/../../classes/dashboard/DashboardDatabase.php';

$job = json_decode((string) stream_get_contents(STDIN), true);
if (!is_array($job) || !isset($job['driver'], $job['id'], $job['count'], $job['secret_hex'])) {
    fwrite(STDERR, "Travail invalide\n");
    exit(2);
}

if ($job['driver'] === 'mysql') {
    $pdo = DashboardDatabase::connect($job['config']);
} else {
    $pdo = new PDO('sqlite:' . $job['path'], null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 5000');
}

$secret = (string) hex2bin((string) $job['secret_hex']);
$ua     = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
$stats  = ['counter_ok' => 0, 'error_ok' => 0, 'failed' => 0, 'codes' => []];

for ($i = 0; $i < (int) $job['count']; $i++) {
    $now = time();

    $requests = [
        'counter_ok' => VisitCollector::buildRequest(
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/accueil/fr', 'REMOTE_ADDR' => '198.51.100.200', 'HTTP_USER_AGENT' => $ua, 'HTTP_HOST' => 'www.example.test', 'HTTP_DNT' => '1'],
            [],
            'home',
            'fr',
            200
        ),
        'error_ok' => VisitCollector::buildRequest(
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/contention', 'REMOTE_ADDR' => '198.51.100.' . (10 + (int) $job['id']), 'HTTP_USER_AGENT' => $ua, 'HTTP_HOST' => 'www.example.test'],
            [],
            null,
            'fr',
            404
        ),
    ];

    foreach ($requests as $counter => $request) {
        try {
            VisitCollector::record($pdo, $secret, $request, $now);
            $stats[$counter]++;
        } catch (Throwable $e) {
            $stats['failed']++;
            $code = VisitCollector::describe($e);
            $stats['codes'][$code] = ($stats['codes'][$code] ?? 0) + 1;
        }
    }
}

echo json_encode($stats);
