<?php
declare(strict_types=1);

/**
 * Fichier : tests/dashboard/test_collector_live.php
 * Rôle : Contrôle de bout en bout de la collecte (G4c) : de vraies requêtes HTTP vers un site LOCAL, puis relecture de
 * la base du dashboard pour vérifier ce qui a été enregistré, et seulement cela.
 *
 * Usage (depuis la racine du projet, Laragon démarré, migrations appliquées, dashboard.config.php en place) :
 *     php tests/dashboard/test_collector_live.php http://localhost/JT-Developpeur
 *
 * Ce que le test envoie (toujours avec le User-Agent du robot de test « JTissier-CheckBot » : profil « test », exclu
 * des statistiques) :
 *   a, b, c  trois pages en 200                  -> 3 compteurs, 3 parcours (une visite ouverte ou continuée)
 *   d        /home/fr (redirigée, 301)           -> 1 compteur, aucun parcours
 *   e        une page inexistante (404)          -> 1 compteur, 1 parcours, aucune erreur (décision D9)
 *   f        une image inexistante (404)         -> rien du tout (D9 : le robot de test n'écrit pas d'erreur 404)
 *   g, h     un POST et un HEAD                  -> rien du tout
 *   i        une page en 200 avec « DNT: 1 »     -> 1 compteur, aucun parcours ni erreur
 * Avant tout cela, une page inexistante demandée par un visiteur NEUF (clé de visite jamais vue) : 1 compteur 404, aucune
 * erreur (D9) et aucune visite ouverte (décision D8 : seule une page en 200 ouvre une visite).
 * Enfin, la même sorte de page inexistante demandée par un robot d'indexation (User-Agent Googlebot, profil « bot ») :
 * 1 erreur, preuve que l'écriture des erreurs fonctionne et que seul le robot de test n'en écrit pas.
 * Il lit ensuite la base (SELECT uniquement) jusqu'à 10 s, le temps que la fin du script PHP écrive, et compare les
 * AVANT/APRÈS. Il ne supprime rien : les lignes « test » restent en base locale (profil exclu des statistiques).
 *
 * Refuse tout site qui n'est pas local (localhost, 127.0.0.1, ::1, *.test, *.local, *.localhost) : la base de
 * production se contrôle dans phpMyAdmin (voir docs/dashboard-collecte.md).
 *
 * Sans dashboard.config.php : section IGNORÉE (la collecte est alors désactivée, c'est voulu).
 * Compte rendu au format des autres tests : « Résultat : N OK, M échec(s) », code de sortie 1 s'il y a un échec.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../classes/dashboard/DashboardConfig.php';
require_once __DIR__ . '/../../classes/dashboard/DashboardDatabase.php';

const LIVE_UA             = 'JTissier-CheckBot/1.0 (robot de test du proprietaire; test_collector_live)';
const LIVE_MARKER         = 'JTissier-CheckBot';
const LIVE_WAIT_SECONDS   = 10;
const LIVE_SLOW_SECONDS   = 8.0;

const LIVE_BOT_UA         = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';

$ok = 0;
$failed = 0;
$ignored = 0;

function liveOk(string $label, bool $passed): void
{
    global $ok, $failed;
    if ($passed) {
        $ok++;
        echo 'OK      ' . $label . "\n";
    } else {
        $failed++;
        echo 'ÉCHEC   ' . $label . "\n";
    }
}

function liveEnd(): void
{
    global $ok, $failed, $ignored;
    echo "\nRésultat : {$ok} OK, {$failed} échec(s), {$ignored} section(s) ignorée(s)\n";
    exit($failed === 0 ? 0 : 1);
}

/**
 * Une requête HTTP sans suivre les redirections, avec le User-Agent du robot de test.
 *
 * @param list<string> $headers
 * @return array{status:int,seconds:float,cookies:string}
 */
function liveRequest(string $url, string $method = 'GET', array $headers = [], string $body = '', ?string $userAgent = null): array
{
    $lines = array_merge(['User-Agent: ' . ($userAgent ?? LIVE_UA), 'Connection: close'], $headers);
    if ($method === 'POST') {
        $lines[] = 'Content-Type: application/x-www-form-urlencoded';
    }
    $context = stream_context_create(['http' => [
        'method'          => $method,
        'header'          => implode("\r\n", $lines),
        'content'         => $body,
        'follow_location' => 0,
        'ignore_errors'   => true,
        'timeout'         => 20,
    ]]);

    $start = microtime(true);
    @file_get_contents($url, false, $context);
    $seconds = microtime(true) - $start;

    // PHP 8.4 et suivants : http_get_last_response_headers() ; avant : la variable $http_response_header
    $received = function_exists('http_get_last_response_headers') ? (http_get_last_response_headers() ?? []) : ($http_response_header ?? []);

    $status = 0;
    $cookies = [];
    foreach ($received as $line) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m) === 1) {
            $status = (int) $m[1];
        } elseif (stripos($line, 'Set-Cookie:') === 0) {
            $cookies[] = trim(explode('=', substr($line, 11), 2)[0]);
        }
    }

    return ['status' => $status, 'seconds' => $seconds, 'cookies' => implode(',', $cookies)];
}

function liveScalar(PDO $pdo, string $sql, array $params = []): int
{
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    $value = $statement->fetchColumn();

    return $value === false || $value === null ? 0 : (int) $value;
}

/**
 * Compteurs du profil « test » et des deux erreurs recherchées, à un instant donné.
 *
 * @return array<string,int>
 */
function liveSnapshot(PDO $pdo, string $pageSuffix, string $staticSuffix): array
{
    $daily = static fn (int $status): int => liveScalar(
        $pdo,
        "SELECT COALESCE(SUM(hits), 0) FROM daily_stats WHERE profile = 'test' AND status = ?",
        [$status]
    );
    $errors = static fn (string $suffix): int => liveScalar(
        $pdo,
        "SELECT COALESCE(SUM(occurrences), 0) FROM errors WHERE kind = 'not_found' AND subject LIKE ?",
        ['%' . $suffix]
    );

    return [
        's200'       => $daily(200),
        's301'       => $daily(301),
        's404'       => $daily(404),
        'visits'     => liveScalar($pdo, "SELECT COUNT(*) FROM visits WHERE profile = 'test'"),
        'hits'       => liveScalar($pdo, "SELECT COUNT(*) FROM hits h JOIN visits v ON v.visit_id = h.visit_id WHERE v.profile = 'test'"),
        'err_page'   => $errors($pageSuffix),
        'err_static' => $errors($staticSuffix),
    ];
}

/**
 * Relit la base jusqu'à ce que les écarts attendus apparaissent (la collecte s'exécute après l'envoi de la page),
 * ou jusqu'à LIVE_WAIT_SECONDS. Renvoie les écarts AVANT/APRÈS de tous les compteurs.
 *
 * @param array<string,int> $before
 * @param array<string,int> $expected
 * @return array<string,int>
 */
function liveSettle(PDO $pdo, string $pageSuffix, string $staticSuffix, array $before, array $expected): array
{
    $deadline = microtime(true) + LIVE_WAIT_SECONDS;
    do {
        $delta = [];
        foreach (liveSnapshot($pdo, $pageSuffix, $staticSuffix) as $name => $value) {
            $delta[$name] = $value - $before[$name];
        }
        $done = true;
        foreach ($expected as $name => $value) {
            $done = $done && $delta[$name] === $value;
        }
        if (!$done) {
            usleep(250000);
        }
    } while (!$done && microtime(true) < $deadline);

    return $delta;
}

// ---------------------------------------------------------------------------------------------------------------------
// Adresse du site : locale uniquement
// ---------------------------------------------------------------------------------------------------------------------
$base = rtrim((string) ($argv[1] ?? ''), '/');
$parts = parse_url($base);
$host = strtolower((string) ($parts['host'] ?? ''));
if ($base === '' || !isset($parts['scheme']) || !in_array($parts['scheme'], ['http', 'https'], true) || $host === '') {
    fwrite(STDERR, "Usage : php tests/dashboard/test_collector_live.php <URL du site local, sans slash final>\n");
    exit(2);
}
$isLocal = in_array($host, ['localhost', '127.0.0.1', '::1', '[::1]'], true) || preg_match('/\.(test|local|localhost)$/', $host) === 1;
if (!$isLocal) {
    fwrite(STDERR, "Refusé : « {$host} » n'est pas un site local. Ce test lit la base de dashboard.config.php, qui est celle de ton poste.\n");
    exit(2);
}

// ---------------------------------------------------------------------------------------------------------------------
// Connexion à la base (la même que celle du site local)
// ---------------------------------------------------------------------------------------------------------------------
echo "== Collecte de bout en bout ({$base})\n";

$seam = $GLOBALS['jtLiveConnect'] ?? null; // point d'entrée réservé aux tests de ce test (base de remplacement)
if (is_callable($seam)) {
    $pdo = $seam();
} else {
    if (DashboardConfig::locate() === null) {
        $ignored++;
        echo "IGNORÉ  aucun dashboard.config.php : la collecte est désactivée, rien à contrôler\n";
        liveEnd();
    }
    try {
        $pdo = DashboardDatabase::connect(DashboardConfig::load());
    } catch (Throwable $e) {
        liveOk('connexion à la base du dashboard (' . get_class($e) . ' : ' . $e->getMessage() . ')', false);
        liveEnd();
    }
}
liveOk('connexion à la base du dashboard', true);

try {
    liveScalar($pdo, 'SELECT COUNT(*) FROM daily_stats');
    liveScalar($pdo, 'SELECT COUNT(*) FROM errors');
    liveOk('tables de la collecte présentes', true);
} catch (Throwable $e) {
    liveOk('tables de la collecte absentes : lancer « php database/migrate.php up » (' . get_class($e) . ')', false);
    liveEnd();
}

// ---------------------------------------------------------------------------------------------------------------------
// Avant / envois / après
// ---------------------------------------------------------------------------------------------------------------------
$token = bin2hex(random_bytes(4));
$pagePath = '/g4-' . $token . '/fr';
$staticPath = '/g4-' . $token . '.png';

// j : une 404 demandée par un visiteur tout neuf (User-Agent propre à ce test, donc clé de visite jamais vue)
$freshPath = '/g4-' . $token . '-neuf/fr';
$beforeFresh = liveSnapshot($pdo, $freshPath, $staticPath);
$fresh = liveRequest($base . $freshPath, 'GET', [], '', LIVE_UA . ' neuf-' . $token);
liveOk('j  GET page inexistante, visiteur neuf -> ' . $fresh['status'] . ' (attendu : 404)', $fresh['status'] === 404);
$deltaFresh = liveSettle($pdo, $freshPath, $staticPath, $beforeFresh, ['s404' => 1, 'err_page' => 0]);

// k : la même sorte de page inexistante, demandée par un robot d'indexation : la seule qui doit laisser une erreur
$botPath = '/g4-' . $token . '-robot/fr';
$beforeBot = liveSnapshot($pdo, $botPath, $staticPath);
$bot = liveRequest($base . $botPath, 'GET', [], '', LIVE_BOT_UA);
liveOk('k  GET page inexistante, robot d\'indexation -> ' . $bot['status'] . ' (attendu : 404)', $bot['status'] === 404);
$deltaBot = liveSettle($pdo, $botPath, $staticPath, $beforeBot, ['err_page' => 1]);

$before = liveSnapshot($pdo, $pagePath, $staticPath);

$sent = [
    ['a  GET /accueil/fr', liveRequest($base . '/accueil/fr'), 200],
    ['b  GET /home/en', liveRequest($base . '/home/en'), 200],
    ['c  GET /mon-parcours/fr', liveRequest($base . '/mon-parcours/fr'), 200],
    ['d  GET /home/fr (redirection)', liveRequest($base . '/home/fr'), 301],
    ['e  GET page inexistante', liveRequest($base . $pagePath), 404],
    ['f  GET image inexistante', liveRequest($base . $staticPath), 404],
    ['g  POST /accueil/fr (non collecté)', liveRequest($base . '/accueil/fr', 'POST', [], 'x=1'), null],
    ['h  HEAD /accueil/fr (non collecté)', liveRequest($base . '/accueil/fr', 'HEAD'), 200],
    ['i  GET /accueil/fr avec DNT: 1', liveRequest($base . '/accueil/fr', 'GET', ['DNT: 1']), 200],
];

$slowest = 0.0;
$cookies = '';
foreach ($sent as [$label, $response, $expected]) {
    $good = $expected === null ? ($response['status'] > 0 && $response['status'] < 500) : $response['status'] === $expected;
    liveOk($label . ' -> ' . $response['status'] . ($expected !== null ? ' (attendu : ' . $expected . ')' : ' (pas d\'erreur serveur)'), $good);
    $slowest = max($slowest, $response['seconds']);
    $cookies .= $response['cookies'];
}
liveOk('aucun cookie dans les ' . count($sent) . ' réponses' . ($cookies !== '' ? ' (reçu : ' . $cookies . ')' : ''), $cookies === '');
printf("        réponse la plus lente : %.2f s\n", $slowest);
liveOk('aucune page ralentie par la collecte (moins de ' . (int) LIVE_SLOW_SECONDS . ' s)', $slowest < LIVE_SLOW_SECONDS);

// La collecte s'exécute après l'envoi de la page : on attend que les valeurs attendues apparaissent
$expected = [
    's200'       => 4,
    's301'       => 1,
    's404'       => 1,
    'hits'       => 4,
    'err_page'   => 0,
    'err_static' => 0,
];
$delta = liveSettle($pdo, $pagePath, $staticPath, $before, $expected);

echo "\n== Ce que la base a enregistré (profil « test » seulement)\n";
liveOk('visiteur neuf : 1 compteur 404 (obtenu : +' . $deltaFresh['s404'] . '), aucune ligne d\'erreur car le robot de test n\'en écrit pas (obtenu : +' . $deltaFresh['err_page'] . ')', $deltaFresh['s404'] === 1 && $deltaFresh['err_page'] === 0);
liveOk('visiteur neuf : une 404 n\'ouvre aucune visite et n\'écrit aucun parcours (visites +' . $deltaFresh['visits'] . ', parcours +' . $deltaFresh['hits'] . ')', $deltaFresh['visits'] === 0 && $deltaFresh['hits'] === 0);
liveOk('3 pages en 200 + 1 page en 200 avec DNT : 4 compteurs 200 (obtenu : +' . $delta['s200'] . ')', $delta['s200'] === 4);
liveOk('1 redirection : 1 compteur 301 (obtenu : +' . $delta['s301'] . ')', $delta['s301'] === 1);
liveOk('1 page inexistante : 1 compteur 404 (obtenu : +' . $delta['s404'] . ')', $delta['s404'] === 1);
liveOk('parcours : a, b, c et la 404 e, pas la redirection ni DNT (obtenu : +' . $delta['hits'] . ' sur 4)', $delta['hits'] === 4);
liveOk('visites : au plus une nouvelle (obtenu : +' . $delta['visits'] . ')', $delta['visits'] === 0 || $delta['visits'] === 1);
liveOk('page inexistante : aucune ligne d\'erreur, le robot de test n\'en écrit pas (obtenu : +' . $delta['err_page'] . ')', $delta['err_page'] === 0);
liveOk('image inexistante : rien du tout, ni compteur ni parcours ni erreur (obtenu : +' . $delta['err_static'] . ')', $delta['err_static'] === 0);
liveOk('robot d\'indexation : sa page inexistante laisse bien 1 ligne d\'erreur (obtenu : +' . $deltaBot['err_page'] . ')', $deltaBot['err_page'] === 1);
liveOk('le POST et le HEAD n\'ont rien ajouté (les totaux ci-dessus sont exacts)', $delta['s200'] === 4 && $delta['hits'] === 4);

// ---------------------------------------------------------------------------------------------------------------------
// Vie privée : la dernière visite « test »
// ---------------------------------------------------------------------------------------------------------------------
echo "\n== Vie privée : la dernière visite du robot de test\n";
$row = $pdo->query(
    "SELECT visit_key, device_type, browser, os, bot_agent, referrer_domain, utm_source, utm_medium, utm_campaign "
    . "FROM visits WHERE profile = 'test' ORDER BY visit_id DESC LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);

if (!is_array($row)) {
    liveOk('une visite « test » existe', false);
    liveEnd();
}
liveOk('clé de visite : 32 caractères hexadécimaux, jamais une adresse', is_string($row['visit_key']) && preg_match('/^[0-9a-f]{32}$/', $row['visit_key']) === 1);
liveOk('agent noté : celui du robot de test', is_string($row['bot_agent']) && strpos($row['bot_agent'], LIVE_MARKER) !== false);
liveOk('appareil : inconnu (rien n\'est deviné pour un robot)', ($row['device_type'] ?? '') === 'unknown');

$looksLikeAddress = static fn ($value): bool => is_string($value) && preg_match('/(?:\d{1,3}\.){3}\d{1,3}|::|(?:[0-9a-f]{1,4}:){3,}/i', $value) === 1;
$leak = false;
foreach (['browser', 'os', 'bot_agent', 'referrer_domain', 'utm_source', 'utm_medium', 'utm_campaign'] as $column) {
    $leak = $leak || $looksLikeAddress($row[$column] ?? null);
}
$errorRows = $pdo->query("SELECT subject, message FROM errors WHERE kind = 'not_found' ORDER BY error_id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
foreach ($errorRows as $errorRow) {
    $leak = $leak || $looksLikeAddress($errorRow['subject'] ?? null) || $looksLikeAddress($errorRow['message'] ?? null);
}
liveOk('aucune adresse IP dans la visite ni dans les 20 dernières erreurs 404', !$leak);

liveEnd();
