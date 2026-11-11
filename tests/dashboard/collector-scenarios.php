<?php
declare(strict_types=1);

/**
 * Fichier : tests/dashboard/collector-scenarios.php
 * Rôle : Les scénarios de test de la collecte (G4b), exécutés sur SQLite (test_visit_collector.php) ET sur une vraie
 * base MariaDB ou MySQL (test_collector_mysql.php) : un scénario qui passe sur l'un et pas sur l'autre révèle une
 * différence de moteur, pas une erreur de test. Ce fichier n'est pas un test : il est chargé par les deux.
 *
 * Fonctions de scénario :
 *   collectorPureScenarios($check)                      sans base : normalisation de la requête
 *   collectorDbScenarios($connect, $check)              VisitCollector sur une base qui a le schéma des migrations
 *   collectorHookScenarios($connect, $check, $workDir)  CollectorHook (freins, configuration, disjoncteur)
 *
 * $check(string $label, bool $condition) enregistre un contrôle.
 * $connect(string $class = PDO::class) retourne une NOUVELLE connexion à la même base de test.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../classes/dashboard/VisitCollector.php';
require_once __DIR__ . '/../../classes/dashboard/CollectorHook.php';
require_once __DIR__ . '/../../classes/dashboard/BurstLimiter.php';
require_once __DIR__ . '/../../classes/dashboard/DashboardConfig.php';
require_once __DIR__ . '/../../classes/dashboard/DashboardDatabase.php';
require_once __DIR__ . '/collector-test-support.php';

const CS_CHROME = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
const CS_IP     = '203.0.113.7';

// ----------------------------------------------------------------------
// Outils
// ----------------------------------------------------------------------

/** Secret serveur de test : 32 octets. */
function cs_secret(): string
{
    return str_repeat("\x11\x22\x33\x44\x55\x66\x77\x88", 4);
}

/** Instant (timestamp) d'une heure locale Europe/Paris. */
function cs_time(string $local): int
{
    return (new DateTimeImmutable($local, new DateTimeZone('Europe/Paris')))->getTimestamp();
}

/**
 * Requête normalisée, comme la produit buildRequest().
 *
 * @param array{server?:array<string,mixed>,query?:array<string,mixed>,page?:?string,lang?:string,status?:int} $o
 * @return array<string,mixed>
 */
function cs_request(array $o = []): array
{
    $server = array_merge([
        'REQUEST_METHOD'  => 'GET',
        'REQUEST_URI'     => '/accueil/fr',
        'REMOTE_ADDR'     => CS_IP,
        'HTTP_USER_AGENT' => CS_CHROME,
        'HTTP_HOST'       => 'www.example.test',
    ], $o['server'] ?? []);

    $page = array_key_exists('page', $o) ? $o['page'] : 'home';

    return VisitCollector::buildRequest($server, $o['query'] ?? [], $page, $o['lang'] ?? 'fr', $o['status'] ?? 200);
}

/**
 * Contexte de CollectorHook::handle().
 *
 * @param array<string,mixed> $o
 * @return array<string,mixed>
 */
function cs_context(array $o = []): array
{
    return [
        'server'     => array_merge([
            'REQUEST_METHOD'  => 'GET',
            'REQUEST_URI'     => '/accueil/fr',
            'REMOTE_ADDR'     => CS_IP,
            'HTTP_USER_AGENT' => CS_CHROME,
            'HTTP_HOST'       => 'www.example.test',
        ], $o['server'] ?? []),
        'query'      => $o['query'] ?? [],
        'page'       => array_key_exists('page', $o) ? $o['page'] : 'home',
        'lang'       => $o['lang'] ?? 'fr',
        'http_code'  => $o['http_code'] ?? 200,
        'last_error' => $o['last_error'] ?? null,
    ];
}

/** Vrai si la colonne existe dans la ligne ET vaut NULL (« ?? » ne distingue pas une colonne absente d'un NULL). */
function cs_is_null($row, string $column): bool
{
    return is_array($row) && array_key_exists($column, $row) && $row[$column] === null;
}

/** @return list<array<string,mixed>> */
function cs_rows(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** @return mixed */
function cs_value(PDO $pdo, string $sql, array $params = [])
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchColumn();
}

function cs_count(PDO $pdo, string $table): int
{
    return (int) cs_value($pdo, 'SELECT COUNT(*) FROM ' . $table);
}

function cs_reset(PDO $pdo): void
{
    foreach (['hits', 'visits', 'login_attempts', 'guided_links', 'daily_stats', 'errors', 'settings'] as $table) {
        $pdo->exec('DELETE FROM ' . $table);
    }
}

/** Tout le contenu de la base, en texte : sert à prouver qu'une valeur n'a été écrite nulle part. */
function cs_dump(PDO $pdo): string
{
    $all = [];
    foreach (['visits', 'hits', 'daily_stats', 'errors', 'settings', 'login_attempts', 'guided_links'] as $table) {
        $all[$table] = cs_rows($pdo, 'SELECT * FROM ' . $table);
    }

    return (string) json_encode($all, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/** Clé de visite attendue. */
function cs_key(string $day, string $ip, string $userAgent): ?string
{
    return VisitPrivacy::key(VisitPrivacy::dailySalt(cs_secret(), $day), $ip, $userAgent);
}

/** Redirige error_log() vers un fichier le temps d'un test. Retourne le chemin du fichier. */
function cs_log_begin(): string
{
    $file = (string) tempnam(sys_get_temp_dir(), 'jt-collector-log-');
    ini_set('error_log', $file);

    return $file;
}

/** Rend le contenu du journal capturé et rétablit la sortie d'erreur standard. */
function cs_log_end(string $file): string
{
    ini_restore('error_log');
    $text = (string) file_get_contents($file);
    @unlink($file);

    return $text;
}

// ----------------------------------------------------------------------
// Sans base : normalisation de la requête
// ----------------------------------------------------------------------

function collectorPureScenarios(callable $check): void
{
    // --- buildRequest
    $r = VisitCollector::buildRequest(
        ['REQUEST_METHOD' => 'get', 'REQUEST_URI' => '/accueil/fr?x=1', 'REMOTE_ADDR' => CS_IP, 'HTTP_USER_AGENT' => 'UA', 'HTTP_HOST' => 'Example.TEST:8080', 'HTTP_REFERER' => 'https://a.test/'],
        [],
        'home',
        'fr',
        200
    );
    $check('requête : méthode en majuscules', $r['method'] === 'GET');
    $check('requête : hôte en minuscules, sans port', $r['host'] === 'example.test');
    $check('requête : adresse, User-Agent, référent et URI repris tels quels pour le calcul', $r['ip'] === CS_IP && $r['user_agent'] === 'UA' && $r['referer'] === 'https://a.test/' && $r['uri'] === '/accueil/fr?x=1');
    $check('requête : page et code repris', $r['page'] === 'home' && $r['status'] === 200);

    $check('requête : langue « en » conservée', cs_request(['lang' => 'en'])['lang'] === 'en');
    $check('requête : toute autre langue devient « fr »', cs_request(['lang' => 'EN'])['lang'] === 'fr' && cs_request(['lang' => 'de'])['lang'] === 'fr' && cs_request(['lang' => ''])['lang'] === 'fr');

    $check('requête : page « cgu-cgv » valide', cs_request(['page' => 'cgu-cgv'])['page'] === 'cgu-cgv');
    $check('requête : page invalide (200) devient « unknown »', cs_request(['page' => '../x'])['page'] === 'unknown' && cs_request(['page' => ''])['page'] === 'unknown' && cs_request(['page' => null])['page'] === 'unknown');
    $check('requête : page trop longue devient « unknown »', cs_request(['page' => str_repeat('a', 33)])['page'] === 'unknown');
    $check('requête : page de 32 caractères acceptée', cs_request(['page' => str_repeat('a', 32)])['page'] === str_repeat('a', 32));
    $check('requête : page absente en 404 devient « 404 »', cs_request(['page' => null, 'status' => 404])['page'] === '404' && cs_request(['page' => '../x', 'status' => 404])['page'] === '404');

    $long = cs_request(['server' => ['REQUEST_URI' => '/' . str_repeat('a', 5000), 'HTTP_USER_AGENT' => str_repeat('b', 2000), 'HTTP_REFERER' => str_repeat('c', 5000)]]);
    $check('requête : URI bornée à 2048 octets', strlen($long['uri']) === 2048);
    $check('requête : User-Agent borné à 512 octets', strlen($long['user_agent']) === 512);
    $check('requête : référent borné à 2048 octets', strlen($long['referer']) === 2048);

    $weird = VisitCollector::buildRequest(
        ['REQUEST_METHOD' => ['x'], 'REQUEST_URI' => 12, 'REMOTE_ADDR' => null, 'HTTP_USER_AGENT' => ['a'], 'HTTP_HOST' => false],
        [],
        'home',
        'fr',
        200
    );
    $check('requête : valeurs qui ne sont pas du texte ramenées à vide', $weird['method'] === '' && $weird['uri'] === '' && $weird['ip'] === '' && $weird['user_agent'] === '' && $weird['host'] === '');

    // --- paramètre creation
    $creation = static function ($value, ?string $page = 'creations', int $status = 200): ?string {
        return cs_request(['page' => $page, 'status' => $status, 'query' => ['creation' => $value]])['creation'];
    };
    $check('creation : identifiant sobre conservé', $creation('mon-projet_1') === 'mon-projet_1');
    $check('creation : 64 caractères acceptés, 65 refusés', $creation(str_repeat('a', 64)) === str_repeat('a', 64) && $creation(str_repeat('a', 65)) === null);
    $check('creation : caractères inhabituels refusés', $creation('../etc') === null && $creation('a b') === null && $creation('a?b') === null && $creation('<x>') === null && $creation('') === null);
    $check('creation : doit commencer par une lettre ou un chiffre', $creation('-x') === null && $creation('_x') === null);
    $check('creation : une liste (creation[]=x) est ignorée', $creation(['x']) === null);
    $check('creation : ignoré hors de la page des créations', $creation('abc', 'home') === null);
    $check('creation : ignoré quand la page est une 404', $creation('abc', null, 404) === null);

    // --- campagne, DNT
    $campaign = cs_request(['query' => ['utm_source' => 'linkedin', 'utm_medium' => '<b>']]);
    $check('requête : paramètres utm sobres conservés, autres écartés', $campaign['utm'] === ['utm_source' => 'linkedin']);
    $check('requête : DNT détecté', cs_request(['server' => ['HTTP_DNT' => '1']])['opted_out'] === true);
    $check('requête : Sec-GPC détecté', cs_request(['server' => ['HTTP_SEC_GPC' => '1']])['opted_out'] === true);
    $check('requête : DNT à 0 n\'est pas un refus', cs_request(['server' => ['HTTP_DNT' => '0']])['opted_out'] === false);

    // --- wanted
    $wanted = static function (string $method, int $status): bool {
        return VisitCollector::wanted(cs_request(['server' => ['REQUEST_METHOD' => $method], 'status' => $status]));
    };
    $check('wanted : GET 200, 301, 404, 500', $wanted('GET', 200) && $wanted('GET', 301) && $wanted('GET', 404) && $wanted('GET', 500));
    $check('wanted : autres codes refusés (302, 304, 403, 410, 503, 0)', !$wanted('GET', 302) && !$wanted('GET', 304) && !$wanted('GET', 403) && !$wanted('GET', 410) && !$wanted('GET', 503) && !$wanted('GET', 0));
    $check('wanted : seule la méthode GET', !$wanted('POST', 200) && !$wanted('HEAD', 200) && !$wanted('OPTIONS', 200) && !$wanted('', 200));

    // --- durées de conservation
    $check('conservation : 13 mois, 90 jours, 30 jours', VisitCollector::RETENTION_DETAIL === 'P13M' && VisitCollector::RETENTION_ERRORS === 'P90D' && VisitCollector::RETENTION_LOGIN === 'P30D');
    $check('codes enregistrés : 200, 301, 404, 500', VisitCollector::STATUSES === [200, 301, 404, 500]);

    // --- describe : jamais le message, qui peut citer une valeur
    $e = new PDOException('Duplicate entry \'' . CS_IP . '\' for key');
    $e->errorInfo = ['23000', 1062, 'Duplicate entry ' . CS_IP];
    $check('describe : code SQLSTATE et code moteur, sans le message', VisitCollector::describe($e) === 'PDO 23000/1062');
    $check('describe : autre exception = sa classe seule', VisitCollector::describe(new RuntimeException('contient ' . CS_IP)) === 'RuntimeException');
    $check('describe : SQLSTATE nettoyé', VisitCollector::describe(self_pdo_exception(["23\n000", 5, 'x'])) === 'PDO 23000/5');

    // --- interblocage : SQLSTATE 40001 seulement
    $check('isDeadlock : 40001 oui', VisitCollector::isDeadlock(self_pdo_exception(['40001', 1213, 'x'])));
    $check('isDeadlock : doublon, erreur ordinaire et exception sans errorInfo non', !VisitCollector::isDeadlock(self_pdo_exception(['23000', 1062, 'x'])) && !VisitCollector::isDeadlock(self_pdo_exception(['HY000', 1205, 'x'])) && !VisitCollector::isDeadlock(new PDOException('x')) && !VisitCollector::isDeadlock(new RuntimeException('x')));

    // --- freins du point d'entrée
    $check('freins : 30 pages 404 et 600 écritures par fenêtre de 60 secondes', CollectorHook::LIMIT_NOT_FOUND === 30 && CollectorHook::LIMIT_WRITES === 600 && CollectorHook::LIMIT_WINDOW_SECONDS === 60);

    // --- statut final d'une page (CollectorHook::resolveStatus)
    $check('statut : 200, 301, 404, 500 repris', CollectorHook::resolveStatus(200, null) === 200 && CollectorHook::resolveStatus(301, null) === 301 && CollectorHook::resolveStatus(404, null) === 404 && CollectorHook::resolveStatus(500, null) === 500);
    $check('statut : hors serveur web (false) = 200', CollectorHook::resolveStatus(false, null) === 200);
    $check('statut : erreur PHP fatale = 500 même si le code dit 200', CollectorHook::resolveStatus(200, ['type' => E_ERROR]) === 500 && CollectorHook::resolveStatus(200, ['type' => E_PARSE]) === 500 && CollectorHook::resolveStatus(200, ['type' => E_COMPILE_ERROR]) === 500 && CollectorHook::resolveStatus(200, ['type' => E_USER_ERROR]) === 500 && CollectorHook::resolveStatus(200, ['type' => E_RECOVERABLE_ERROR]) === 500 && CollectorHook::resolveStatus(200, ['type' => E_CORE_ERROR]) === 500);
    $check('statut : simple avertissement ou notice = code de la réponse', CollectorHook::resolveStatus(200, ['type' => E_WARNING]) === 200 && CollectorHook::resolveStatus(404, ['type' => E_NOTICE]) === 404 && CollectorHook::resolveStatus(200, ['type' => E_DEPRECATED]) === 200 && CollectorHook::resolveStatus(200, ['type' => E_USER_WARNING]) === 200);
    $check('statut : erreur fatale sur une 404 = 500', CollectorHook::resolveStatus(404, ['type' => E_ERROR]) === 500);
    $check('statut : code inhabituel conservé pour que la collecte l\'ignore', CollectorHook::resolveStatus(302, null) === 302 && CollectorHook::resolveStatus(403, null) === 403);
}

/** @param array<int,mixed> $errorInfo */
function self_pdo_exception(array $errorInfo): PDOException
{
    $e = new PDOException('x');
    $e->errorInfo = $errorInfo;

    return $e;
}

// ----------------------------------------------------------------------
// VisitCollector sur une base
// ----------------------------------------------------------------------

function collectorDbScenarios(callable $connect, callable $check): void
{
    $pdo    = $connect();
    $secret = cs_secret();
    $rec    = static function (array $o, int $now) use ($pdo, $secret): string {
        return VisitCollector::record($pdo, $secret, cs_request($o), $now);
    };

    cs_scenario_visits($pdo, $rec, $check);
    cs_scenario_filters($pdo, $rec, $check);
    cs_scenario_errors($pdo, $rec, $check);
    cs_scenario_texts($pdo, $rec, $check);
    cs_scenario_rotation($pdo, $connect, $rec, $check);
    cs_scenario_purge($pdo, $check);
    cs_scenario_failures($pdo, $connect, $rec, $check);
    cs_scenario_model($pdo, $check);

    cs_reset($pdo);
}

/** @param callable(array,int):string $rec */
function cs_scenario_visits(PDO $pdo, callable $rec, callable $check): void
{
    $t0     = cs_time('2026-11-10 10:00:00');
    $client = VisitClassifier::classify(CS_CHROME);

    // --- une première page ouvre une visite
    cs_reset($pdo);
    $check('visite : première page 200 = visit_started', $rec([], $t0) === 'visit_started');

    $visits = cs_rows($pdo, 'SELECT * FROM visits');
    $check('visite : une seule ligne', count($visits) === 1);
    $v = $visits[0] ?? [];
    $check('visite : clé = HMAC du jour, de l\'adresse et du User-Agent', ($v['visit_key'] ?? null) === cs_key('2026-11-10', CS_IP, CS_CHROME));
    $check('visite : jour au fuseau de Paris', ($v['visit_day'] ?? '') === '2026-11-10');
    $check('visite : heures en UTC (10 h à Paris = 09 h UTC en novembre)', ($v['started_at'] ?? '') === '2026-11-10 09:00:00' && ($v['last_hit_at'] ?? '') === '2026-11-10 09:00:00');
    $check('visite : profil humain, une page', ($v['profile'] ?? '') === 'human' && (int) ($v['hit_count'] ?? 0) === 1);
    $check('visite : appareil, navigateur et système en familles', ($v['device_type'] ?? '') === $client['device_type'] && ($v['browser'] ?? '') === $client['browser'] && ($v['os'] ?? '') === $client['os']);
    $check('visite : ni robot, ni origine, ni campagne', cs_is_null($v, 'bot_agent') && cs_is_null($v, 'referrer_domain') && cs_is_null($v, 'utm_source') && cs_is_null($v, 'guided_id'));

    $hits = cs_rows($pdo, 'SELECT * FROM hits');
    $check('parcours : une page', count($hits) === 1 && (int) $hits[0]['visit_id'] === (int) ($v['visit_id'] ?? -1));
    $check('parcours : page, langue, code, heure', ($hits[0]['page'] ?? '') === 'home' && ($hits[0]['lang'] ?? '') === 'fr' && (int) ($hits[0]['status'] ?? 0) === 200 && ($hits[0]['hit_at'] ?? '') === '2026-11-10 09:00:00' && cs_is_null($hits[0], 'creation_id'));

    $daily = cs_rows($pdo, 'SELECT * FROM daily_stats');
    $check('compteur : une ligne, un passage', count($daily) === 1 && (int) $daily[0]['hits'] === 1 && $daily[0]['stat_date'] === '2026-11-10' && $daily[0]['profile'] === 'human' && $daily[0]['page'] === 'home' && $daily[0]['lang'] === 'fr' && (int) $daily[0]['status'] === 200);

    $dump = cs_dump($pdo);
    $check('vie privée : l\'adresse IP n\'est écrite nulle part', strpos($dump, CS_IP) === false && strpos($dump, '203.0.113') === false);
    $check('vie privée : le User-Agent d\'un humain n\'est écrit nulle part', strpos($dump, 'Chrome/120') === false && strpos($dump, 'AppleWebKit') === false && strpos($dump, 'Mozilla') === false);

    // --- pages suivantes
    cs_reset($pdo);
    $rec([], $t0);
    $check('visite : page suivante à +60 s = visit_continued', $rec(['page' => 'career', 'server' => ['REQUEST_URI' => '/parcours/fr']], $t0 + 60) === 'visit_continued');
    $check('visite : même page encore = visit_continued', $rec([], $t0 + 120) === 'visit_continued');
    $v = cs_rows($pdo, 'SELECT * FROM visits')[0];
    $check('visite : toujours une seule, 3 pages, dernière page à +120 s', cs_count($pdo, 'visits') === 1 && (int) $v['hit_count'] === 3 && $v['last_hit_at'] === '2026-11-10 09:02:00' && $v['started_at'] === '2026-11-10 09:00:00');
    $check('parcours : 3 pages dans l\'ordre', array_column(cs_rows($pdo, 'SELECT page FROM hits ORDER BY hit_id'), 'page') === ['home', 'career', 'home']);
    $check('compteur : home 2, career 1', cs_value($pdo, "SELECT hits FROM daily_stats WHERE page = 'home'") == 2 && cs_value($pdo, "SELECT hits FROM daily_stats WHERE page = 'career'") == 1 && cs_count($pdo, 'daily_stats') === 2);

    // --- inactivité : 30 minutes
    cs_reset($pdo);
    $rec([], $t0);
    $check('inactivité : 29 min 59 s plus tard, même visite', $rec([], $t0 + 1799) === 'visit_continued');
    $check('inactivité : 30 min après la dernière page, nouvelle visite', $rec([], $t0 + 1799 + 1800) === 'visit_started');
    $check('inactivité : deux visites, même clé', cs_count($pdo, 'visits') === 2 && cs_value($pdo, 'SELECT COUNT(DISTINCT visit_key) FROM visits') == 1);
    $check('inactivité : la page suivante rejoint la plus récente', $rec([], $t0 + 1799 + 1800 + 10) === 'visit_continued' && cs_value($pdo, 'SELECT hit_count FROM visits ORDER BY visit_id DESC LIMIT 1') == 2 && cs_value($pdo, 'SELECT hit_count FROM visits ORDER BY visit_id ASC LIMIT 1') == 2);

    // --- minuit
    cs_reset($pdo);
    $rec([], cs_time('2026-11-10 23:50:00'));
    $check('minuit : 15 min plus tard mais le lendemain, nouvelle visite', $rec([], cs_time('2026-11-11 00:05:00')) === 'visit_started');
    $keys = cs_rows($pdo, 'SELECT visit_key, visit_day FROM visits ORDER BY visit_id');
    $check('minuit : deux jours, deux visites', count($keys) === 2 && $keys[0]['visit_day'] === '2026-11-10' && $keys[1]['visit_day'] === '2026-11-11');
    $check('minuit : la clé de la veille est effacée par la première requête du jour, celle du jour est celle du nouveau sel', $keys[0]['visit_key'] === null && $keys[1]['visit_key'] === cs_key('2026-11-11', CS_IP, CS_CHROME) && cs_key('2026-11-11', CS_IP, CS_CHROME) !== cs_key('2026-11-10', CS_IP, CS_CHROME));

    // --- visiteurs différents
    cs_reset($pdo);
    $rec([], $t0);
    $rec(['server' => ['REMOTE_ADDR' => '198.51.100.20']], $t0 + 1);
    $rec(['server' => ['HTTP_USER_AGENT' => CS_CHROME . ' Edg/120']], $t0 + 2);
    $check('visiteurs : autre adresse ou autre navigateur = autre visite', cs_count($pdo, 'visits') === 3 && cs_value($pdo, 'SELECT COUNT(DISTINCT visit_key) FROM visits') == 3);

    cs_reset($pdo);
    $rec(['server' => ['REMOTE_ADDR' => '2001:db8:abcd:12::1']], $t0);
    $same = $rec(['server' => ['REMOTE_ADDR' => '2001:db8:abcd:12:ffff:eeee:dddd:2']], $t0 + 5);
    $other = $rec(['server' => ['REMOTE_ADDR' => '2001:db8:abcd:13::1']], $t0 + 6);
    $check('IPv6 : même préfixe /64 = même visiteur, autre préfixe = autre visiteur', $same === 'visit_continued' && $other === 'visit_started' && cs_count($pdo, 'visits') === 2);
    $check('vie privée : aucune adresse IPv6 écrite', strpos(cs_dump($pdo), '2001:db8') === false);

    // --- adresse invalide : compteur seul
    cs_reset($pdo);
    $check('adresse invalide : compteur seul', $rec(['server' => ['REMOTE_ADDR' => 'not-an-ip']], $t0) === 'counted' && $rec(['server' => ['REMOTE_ADDR' => '']], $t0) === 'counted');
    $check('adresse invalide : aucune visite, deux pages comptées', cs_count($pdo, 'visits') === 0 && cs_count($pdo, 'hits') === 0 && cs_value($pdo, 'SELECT SUM(hits) FROM daily_stats') == 2);

    // --- identifiants de création
    cs_reset($pdo);
    $rec(['page' => 'creations', 'query' => ['creation' => 'mon-projet_1'], 'server' => ['REQUEST_URI' => '/creations/fr?creation=mon-projet_1']], $t0);
    $rec(['page' => 'creations', 'query' => ['creation' => '../etc'], 'server' => ['REQUEST_URI' => '/creations/fr']], $t0 + 5);
    $rec(['page' => 'home', 'query' => ['creation' => 'abc']], $t0 + 10);
    $rec(['page' => null, 'status' => 404, 'query' => ['creation' => 'inconnu'], 'server' => ['REQUEST_URI' => '/creations/fr?creation=inconnu']], $t0 + 15);
    $creations = array_column(cs_rows($pdo, 'SELECT creation_id FROM hits ORDER BY hit_id'), 'creation_id');
    $check('création : seul l\'identifiant d\'une page 200 valide est écrit', $creations === ['mon-projet_1', null, null, null]);

    cs_reset($pdo);
    $rec(['page' => 'creations', 'query' => ['creation' => 'abc'], 'server' => ['REQUEST_URI' => '/creations/fr?creation=abc']], $t0);
    $rec(['page' => 'creations', 'status' => 500, 'query' => ['creation' => 'abc'], 'server' => ['REQUEST_URI' => '/creations/fr?creation=abc']], $t0 + 5);
    $check('création : une page en erreur 500 n\'enregistre pas l\'identifiant', array_column(cs_rows($pdo, 'SELECT creation_id FROM hits ORDER BY hit_id'), 'creation_id') === ['abc', null]);
}

/** @param callable(array,int):string $rec */
function cs_scenario_filters(PDO $pdo, callable $rec, callable $check): void
{
    $t0 = cs_time('2026-11-10 10:00:00');

    // --- DNT et Sec-GPC : le compteur seul
    foreach (['HTTP_DNT', 'HTTP_SEC_GPC'] as $header) {
        cs_reset($pdo);
        $r = $rec(['server' => [$header => '1']], $t0);
        $check("{$header} : compteur seul", $r === 'counted');
        $check("{$header} : aucune visite, aucun parcours, une page comptée", cs_count($pdo, 'visits') === 0 && cs_count($pdo, 'hits') === 0 && cs_value($pdo, 'SELECT SUM(hits) FROM daily_stats') == 1);

        $r404 = $rec(['server' => [$header => '1', 'REQUEST_URI' => '/inconnue'], 'page' => null, 'status' => 404], $t0 + 1);
        $check("{$header} : une 404 est comptée mais son chemin n'est pas noté", $r404 === 'counted' && cs_count($pdo, 'errors') === 0 && cs_value($pdo, "SELECT hits FROM daily_stats WHERE status = 404") == 1);
        $r500 = $rec(['server' => [$header => '1'], 'status' => 500], $t0 + 2);
        $check("{$header} : une 500 est comptée sans erreur notée", $r500 === 'counted' && cs_count($pdo, 'errors') === 0);
        $check("{$header} : une 404 de fichier statique est ignorée", $rec(['server' => [$header => '1', 'REQUEST_URI' => '/assets/x.css'], 'page' => null, 'status' => 404], $t0 + 3) === 'skipped' && cs_count($pdo, 'errors') === 0);
    }

    cs_reset($pdo);
    $rec([], $t0);
    $rec(['server' => ['HTTP_DNT' => '1']], $t0 + 10);
    $check('DNT : une page d\'un visiteur qui l\'envoie ne rejoint pas la visite en cours', cs_value($pdo, 'SELECT hit_count FROM visits') == 1 && cs_count($pdo, 'hits') === 1);

    cs_reset($pdo);
    $check('DNT à 0 : visite normale', $rec(['server' => ['HTTP_DNT' => '0']], $t0) === 'visit_started');

    // --- redirection 301 : compteur seul, même si une visite est en cours
    cs_reset($pdo);
    $check('301 sans visite : compteur seul', $rec(['status' => 301, 'server' => ['REQUEST_URI' => '/accueil']], $t0) === 'counted');
    $check('301 : aucune visite, compteur au code 301', cs_count($pdo, 'visits') === 0 && cs_value($pdo, 'SELECT hits FROM daily_stats WHERE status = 301') == 1);
    $rec([], $t0 + 5);
    $check('301 avec visite : compteur seul', $rec(['status' => 301], $t0 + 10) === 'counted' && cs_value($pdo, 'SELECT hit_count FROM visits') == 1 && cs_count($pdo, 'hits') === 1);

    // --- requêtes ignorées
    cs_reset($pdo);
    $ignored = [
        'POST'        => ['server' => ['REQUEST_METHOD' => 'POST']],
        'HEAD'        => ['server' => ['REQUEST_METHOD' => 'HEAD']],
        'code 302'    => ['status' => 302],
        'code 403'    => ['status' => 403],
        'code 503'    => ['status' => 503],
    ];
    foreach ($ignored as $label => $options) {
        $check("ignorée ({$label}) : skipped", $rec($options, $t0) === 'skipped');
    }
    $check('ignorées : rien n\'est écrit, pas même un réglage', cs_dump($pdo) === cs_dump_empty());

    // --- robots et robot de test
    cs_reset($pdo);
    $bot = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';
    $rec(['server' => ['HTTP_USER_AGENT' => $bot]], $t0);
    $classified = VisitClassifier::classify($bot);
    $v = cs_rows($pdo, 'SELECT * FROM visits')[0] ?? [];
    $check('robot : profil bot, nom du robot', ($v['profile'] ?? '') === 'bot' && ($v['bot_agent'] ?? null) === VisitText::userAgent((string) $classified['bot_agent']) && $classified['bot_agent'] !== null);
    $check('robot : compteur au profil bot', cs_value($pdo, "SELECT hits FROM daily_stats WHERE profile = 'bot'") == 1);

    cs_reset($pdo);
    $rec(['server' => ['HTTP_USER_AGENT' => 'Mozilla/5.0 JTissier-CheckBot/1.0']], $t0);
    $check('robot de test : profil test', cs_value($pdo, 'SELECT profile FROM visits') === 'test' && cs_value($pdo, "SELECT hits FROM daily_stats WHERE profile = 'test'") == 1);

    // --- origine et campagne
    cs_reset($pdo);
    $rec(['server' => ['HTTP_REFERER' => 'https://www.google.com/search?q=secret+keywords#top'], 'query' => ['utm_source' => 'linkedin', 'utm_medium' => 'social', 'utm_campaign' => 'cv-2026']], $t0);
    $v = cs_rows($pdo, 'SELECT * FROM visits')[0] ?? [];
    $check('origine : domaine seul, sans « www. »', ($v['referrer_domain'] ?? '') === 'google.com');
    $check('campagne : source, support, campagne', ($v['utm_source'] ?? '') === 'linkedin' && ($v['utm_medium'] ?? '') === 'social' && ($v['utm_campaign'] ?? '') === 'cv-2026');
    $check('vie privée : ni mots-clés ni chemin du référent', strpos(cs_dump($pdo), 'secret') === false && strpos(cs_dump($pdo), 'search') === false);

    cs_reset($pdo);
    $rec(['server' => ['HTTP_REFERER' => 'https://www.example.test/accueil/fr', 'HTTP_HOST' => 'example.test'], 'query' => ['utm_source' => '<script>']], $t0);
    $v = cs_rows($pdo, 'SELECT * FROM visits')[0] ?? [];
    $check('origine : navigation interne ignorée, campagne douteuse écartée', cs_is_null($v, 'referrer_domain') && cs_is_null($v, 'utm_source'));
}

/** Contenu de cs_dump() pour une base vide. */
function cs_dump_empty(): string
{
    return '{"visits":[],"hits":[],"daily_stats":[],"errors":[],"settings":[],"login_attempts":[],"guided_links":[]}';
}

/** @param callable(array,int):string $rec */
function cs_scenario_errors(PDO $pdo, callable $rec, callable $check): void
{
    $t0 = cs_time('2026-11-10 10:00:00');

    // --- 404 d'une page
    cs_reset($pdo);
    $notFound = ['page' => null, 'status' => 404, 'server' => ['REQUEST_URI' => '/wp-login.php?token=secret-token-123']];
    $check('404 : sans visite, compteur et erreur', $rec($notFound, $t0) === 'counted');
    $daily = cs_rows($pdo, 'SELECT * FROM daily_stats');
    $check('404 : compteur au code 404, page « 404 »', count($daily) === 1 && $daily[0]['page'] === '404' && (int) $daily[0]['status'] === 404);
    $err = cs_rows($pdo, 'SELECT * FROM errors');
    $check('404 : erreur not_found au chemin demandé, sans la requête', count($err) === 1 && $err[0]['kind'] === 'not_found' && $err[0]['subject'] === '/wp-login.php' && (int) $err[0]['occurrences'] === 1 && $err[0]['error_day'] === '2026-11-10');
    $check('404 : aucune visite n\'est ouverte par une 404', cs_count($pdo, 'visits') === 0 && cs_count($pdo, 'hits') === 0);
    $check('vie privée : le jeton de la requête n\'est écrit nulle part', strpos(cs_dump($pdo), 'secret-token') === false);

    $rec($notFound, $t0 + 30);
    $err = cs_rows($pdo, 'SELECT * FROM errors');
    $check('404 : même chemin, même jour = une seule ligne, 2 occurrences', count($err) === 1 && (int) $err[0]['occurrences'] === 2 && $err[0]['first_at'] === '2026-11-10 09:00:00' && $err[0]['last_at'] === '2026-11-10 09:00:30');

    $rec(['page' => null, 'status' => 404, 'server' => ['REQUEST_URI' => '/admin']], $t0 + 40);
    $check('404 : autre chemin = autre ligne', cs_count($pdo, 'errors') === 2);
    $rec($notFound, cs_time('2026-11-11 10:00:00'));
    $check('404 : même chemin le lendemain = nouvelle ligne', cs_count($pdo, 'errors') === 3 && cs_value($pdo, "SELECT occurrences FROM errors WHERE error_day = '2026-11-11'") == 1);
    $check('404 : compteur quotidien = 3 le premier jour', cs_value($pdo, "SELECT hits FROM daily_stats WHERE stat_date = '2026-11-10'") == 3);

    // --- 404 et 500 dans une visite en cours
    cs_reset($pdo);
    $rec([], $t0);
    $r = $rec(['page' => null, 'status' => 404, 'server' => ['REQUEST_URI' => '/lien-casse']], $t0 + 10);
    $check('404 dans une visite : ajoutée au parcours', $r === 'visit_continued' && cs_value($pdo, 'SELECT hit_count FROM visits') == 2);
    $hit = cs_rows($pdo, 'SELECT * FROM hits ORDER BY hit_id DESC LIMIT 1')[0] ?? [];
    $check('404 dans une visite : page « 404 », code 404', ($hit['page'] ?? '') === '404' && (int) ($hit['status'] ?? 0) === 404 && cs_is_null($hit, 'creation_id'));
    $check('404 dans une visite : erreur notée aussi', cs_value($pdo, "SELECT occurrences FROM errors WHERE kind = 'not_found'") == 1);

    $r = $rec(['status' => 500], $t0 + 20);
    $check('500 dans une visite : ajoutée au parcours, erreur server_error', $r === 'visit_continued' && cs_value($pdo, "SELECT occurrences FROM errors WHERE kind = 'server_error'") == 1 && cs_value($pdo, 'SELECT status FROM hits ORDER BY hit_id DESC LIMIT 1') == 500);

    cs_reset($pdo);
    $check('500 sans visite : compteur et erreur, pas de visite', $rec(['status' => 500], $t0) === 'counted' && cs_count($pdo, 'visits') === 0 && cs_value($pdo, "SELECT hits FROM daily_stats WHERE status = 500") == 1 && cs_value($pdo, "SELECT subject FROM errors WHERE kind = 'server_error'") === '/accueil/fr');

    // --- fichiers statiques introuvables : un lien cassé, pas une page vue
    cs_reset($pdo);
    $static = static function (string $uri) use ($rec, $t0): string {
        return $rec(['page' => null, 'status' => 404, 'server' => ['REQUEST_URI' => $uri]], $t0);
    };
    $check('statique : feuille de style = static_404', $static('/assets/css/absente.css') === 'static_404');
    $check('statique : seule une erreur not_found est notée', cs_count($pdo, 'daily_stats') === 0 && cs_count($pdo, 'visits') === 0 && cs_value($pdo, "SELECT subject FROM errors WHERE kind = 'not_found'") === '/assets/css/absente.css');
    $check('statique : extension en majuscules, requête ignorée', $static('/img/Logo.PNG?v=3') === 'static_404' && cs_value($pdo, "SELECT COUNT(*) FROM errors WHERE subject = '/img/Logo.PNG'") == 1);
    $check('statique : pdf, js, ico, woff2, map', $static('/cv.pdf') === 'static_404' && $static('/a/b.js') === 'static_404' && $static('/favicon.ico') === 'static_404' && $static('/f.woff2') === 'static_404' && $static('/a.js.map') === 'static_404');
    $check('statique : une page sans extension est une page vue', $static('/inconnue') === 'counted');
    $check('statique : « .php » n\'est pas un fichier statique', $static('/wp-login.php') === 'counted' && $static('/xmlrpc.php') === 'counted');
    $check('statique : le point doit être dans le dernier segment', $static('/a.css/b') === 'counted');
    $check('statique : jamais d\'ouverture de visite', cs_count($pdo, 'visits') === 0);
}

/** @param callable(array,int):string $rec */
function cs_scenario_texts(PDO $pdo, callable $rec, callable $check): void
{
    $t0 = cs_time('2026-11-10 10:00:00');

    cs_reset($pdo);
    $garbage = "/\xFF\xFE\x00bad\x1B[31m\u{202E}/" . str_repeat('é', 3000);
    $ok = true;
    try {
        $rec(['page' => null, 'status' => 404, 'server' => ['REQUEST_URI' => $garbage]], $t0);
    } catch (Throwable $e) {
        $ok = false;
    }
    $check('textes : octets invalides, caractères de contrôle et chemin géant n\'empêchent pas l\'écriture', $ok);
    $subject = (string) cs_value($pdo, 'SELECT subject FROM errors');
    $check('textes : sujet en UTF-8 valide, 255 caractères au plus, sans caractère de contrôle', preg_match('//u', $subject) === 1 && preg_match('/^.{1,255}$/su', $subject) === 1 && preg_match('/[\p{Cc}\p{Cf}]/u', $subject) === 0 && $subject[0] === '/');

    cs_reset($pdo);
    $rec(['page' => null, 'status' => 404, 'server' => ['REQUEST_URI' => '/' . str_repeat('a', 600)]], $t0);
    $check('textes : chemin ASCII de 600 caractères borné à 255', strlen((string) cs_value($pdo, 'SELECT subject FROM errors')) === 255);

    cs_reset($pdo);
    $agent = 'Mozilla/5.0 (compatible; ' . str_repeat('SuperBot-é', 80) . '/1.0)';
    $rec(['server' => ['HTTP_USER_AGENT' => $agent]], $t0);
    $stored = cs_value($pdo, 'SELECT bot_agent FROM visits');
    $check('textes : nom de robot borné à 255 caractères', $stored === null || preg_match('/^.{0,255}$/su', (string) $stored) === 1);
}

/**
 * @param callable(string=):PDO $connect
 * @param callable(array,int):string $rec
 */
function cs_scenario_rotation(PDO $pdo, callable $connect, callable $rec, callable $check): void
{
    $yesterday = cs_time('2026-11-09 10:00:00');
    $today     = cs_time('2026-11-10 10:00:00');

    // --- première exécution : les réglages n'existent pas encore
    cs_reset($pdo);
    $check('rotation : première exécution ever = true', VisitCollector::rotate($pdo, $today) === true);
    $check('rotation : réglage enregistré au jour courant', cs_value($pdo, "SELECT value FROM settings WHERE name = 'last_rotation'") === '2026-11-10');
    $check('rotation : deuxième appel le même jour = false', VisitCollector::rotate($pdo, $today + 600) === false);
    $check('purge : première exécution ever = true, puis false', VisitCollector::purge($pdo, $today) === true && VisitCollector::purge($pdo, $today + 600) === false);
    $check('purge : réglage enregistré au jour courant', cs_value($pdo, "SELECT value FROM settings WHERE name = 'last_purge'") === '2026-11-10');

    // --- la première requête du jour efface les clés des jours passés
    cs_reset($pdo);
    $rec([], $yesterday);
    $check('rotation : la première requête enregistre le jour', cs_value($pdo, "SELECT value FROM settings WHERE name = 'last_rotation'") === '2026-11-09');

    $insert = 'INSERT INTO login_attempts (attempted_at, visit_key, succeeded) VALUES (?, ?, 0)';
    $pdo->prepare($insert)->execute(['2026-11-09 22:59:59', str_repeat('a', 32)]);
    $pdo->prepare($insert)->execute(['2026-11-09 23:00:00', str_repeat('b', 32)]);

    $rec(['server' => ['REMOTE_ADDR' => '198.51.100.9']], cs_time('2026-11-10 09:30:00'));
    $keys = cs_rows($pdo, 'SELECT visit_day, visit_key FROM visits ORDER BY visit_id');
    $check('rotation : clé de la veille effacée, clé du jour conservée', count($keys) === 2 && $keys[0]['visit_key'] === null && $keys[1]['visit_key'] !== null && $keys[1]['visit_day'] === '2026-11-10');
    $check('rotation : la visite de la veille reste (sans clé)', $keys[0]['visit_day'] === '2026-11-09' && cs_value($pdo, "SELECT hit_count FROM visits WHERE visit_day = '2026-11-09'") == 1);
    $check('rotation : réglage au jour courant', cs_value($pdo, "SELECT value FROM settings WHERE name = 'last_rotation'") === '2026-11-10');
    $attempts = cs_rows($pdo, 'SELECT attempted_at, visit_key FROM login_attempts ORDER BY attempted_at');
    $check('rotation : tentatives de connexion avant minuit (heure de Paris) sans clé, après minuit conservée', $attempts[0]['visit_key'] === null && $attempts[1]['visit_key'] === str_repeat('b', 32) && count($attempts) === 2);

    // --- le réglage n'est pas réécrit quand tout est fait
    $pdo->exec("UPDATE settings SET updated_at = '2000-01-01 00:00:00'");
    VisitCollector::maintain($pdo, $today);
    $check('maintenance : rien n\'est réécrit une fois le jour fait', cs_value($pdo, 'SELECT COUNT(*) FROM settings WHERE updated_at = \'2000-01-01 00:00:00\'') == 2);

    // --- une rotation qui échoue ne marque pas le jour comme fait
    cs_reset($pdo);
    $rec([], $yesterday);
    $key = (string) cs_value($pdo, 'SELECT visit_key FROM visits');

    $bad = $connect(CollectorTestPdo::class);
    $bad->failOn('/SET visit_key = NULL/', 1);
    $log = cs_log_begin();
    $written = VisitCollector::record($bad, cs_secret(), cs_request(['server' => ['REMOTE_ADDR' => '198.51.100.9']]), $today);
    $text = cs_log_end($log);

    $check('rotation en échec : la page est quand même enregistrée, l\'erreur est journalisée', $written === 'visit_started' && strpos($text, 'effacement des clés de visite impossible') !== false);
    $check('rotation en échec : le jour n\'est pas marqué comme fait, la clé de la veille est toujours là', cs_value($pdo, "SELECT value FROM settings WHERE name = 'last_rotation'") === '2026-11-09' && cs_value($pdo, "SELECT visit_key FROM visits WHERE visit_day = '2026-11-09'") === $key);
    $check('rotation en échec : la purge, indépendante, a eu lieu', cs_value($pdo, "SELECT value FROM settings WHERE name = 'last_purge'") === '2026-11-10');
    $check('rotation en échec : le journal ne contient aucune clé', strpos($text, $key) === false && strpos($text, CS_IP) === false);

    VisitCollector::maintain($pdo, $today + 60);
    $check('rotation en échec : la requête suivante efface la clé de la veille', cs_value($pdo, "SELECT visit_key FROM visits WHERE visit_day = '2026-11-09'") === null && cs_value($pdo, "SELECT value FROM settings WHERE name = 'last_rotation'") === '2026-11-10');
    $check('rotation rattrapée : la visite du jour, écrite entre-temps, garde sa clé', cs_value($pdo, "SELECT visit_key FROM visits WHERE visit_day = '2026-11-10'") === cs_key('2026-11-10', '198.51.100.9', CS_CHROME));
}

/** @param callable(string,bool=):void $check */
function cs_scenario_purge(PDO $pdo, callable $check): void
{
    $addVisit = static function (string $day) use ($pdo): int {
        $pdo->prepare("INSERT INTO visits (visit_key, visit_day, profile, started_at, last_hit_at, hit_count, device_type) VALUES (NULL, ?, 'human', ?, ?, 1, 'desktop')")
            ->execute([$day, $day . ' 10:00:00', $day . ' 10:00:00']);
        $id = (int) $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO hits (visit_id, hit_at, page, lang, status) VALUES (?, ?, 'home', 'fr', 200)")->execute([$id, $day . ' 10:00:00']);

        return $id;
    };
    $addError = static function (string $day) use ($pdo): void {
        $pdo->prepare("INSERT INTO errors (error_day, kind, subject, message, occurrences, first_at, last_at) VALUES (?, 'not_found', ?, '', 1, ?, ?)")
            ->execute([$day, '/' . $day, $day . ' 10:00:00', $day . ' 10:00:00']);
    };
    $addLogin = static function (string $at) use ($pdo): void {
        $pdo->prepare('INSERT INTO login_attempts (attempted_at, visit_key, succeeded) VALUES (?, NULL, 0)')->execute([$at]);
    };

    cs_reset($pdo);
    $addVisit('2025-10-09');   // 13 mois et 1 jour avant le 10/11/2026 : supprimée
    $addVisit('2025-10-10');   // 13 mois pile : conservée
    $addError('2026-08-11');   // 91 jours : supprimée
    $addError('2026-08-12');   // 90 jours : conservée
    $addLogin('2026-10-10 21:59:59');   // avant le 11/10 à minuit (heure d'été) : supprimée
    $addLogin('2026-10-10 22:00:00');   // le 11/10 à 00:00 à Paris : conservée
    $pdo->exec("INSERT INTO daily_stats (stat_date, profile, page, lang, status, hits) VALUES ('2020-01-01', 'human', 'home', 'fr', 200, 7)");

    $check('purge : exécutée', VisitCollector::purge($pdo, cs_time('2026-11-10 10:00:00')) === true);
    $check('purge : visites de plus de 13 mois supprimées, avec leur parcours (cascade)', array_column(cs_rows($pdo, 'SELECT visit_day FROM visits'), 'visit_day') === ['2025-10-10'] && cs_count($pdo, 'hits') === 1);
    $check('purge : erreurs de plus de 90 jours supprimées', array_column(cs_rows($pdo, 'SELECT error_day FROM errors'), 'error_day') === ['2026-08-12']);
    $check('purge : tentatives de connexion de plus de 30 jours supprimées (limite à minuit, heure de Paris)', array_column(cs_rows($pdo, 'SELECT attempted_at FROM login_attempts'), 'attempted_at') === ['2026-10-10 22:00:00']);
    $check('purge : les compteurs quotidiens sont conservés', cs_value($pdo, 'SELECT hits FROM daily_stats') == 7);

    $addVisit('2025-09-01');
    $check('purge : une fois par jour', VisitCollector::purge($pdo, cs_time('2026-11-10 18:00:00')) === false && cs_count($pdo, 'visits') === 2);
    $check('purge : le lendemain, elle recommence et la fenêtre avance d\'un jour (le 10/10/2025 sort aussi)', VisitCollector::purge($pdo, cs_time('2026-11-11 10:00:00')) === true && cs_count($pdo, 'visits') === 0);

    // --- le 31 mars moins 13 mois n'existe pas : la date limite est plus récente, on garde moins, jamais plus
    cs_reset($pdo);
    $addVisit('2025-03-02');
    $addVisit('2025-03-03');
    VisitCollector::purge($pdo, cs_time('2026-03-31 12:00:00'));
    $check('purge : le 31/03/2026, le 02/03/2025 est supprimé et le 03/03/2025 conservé', array_column(cs_rows($pdo, 'SELECT visit_day FROM visits'), 'visit_day') === ['2025-03-03']);

    // --- erreurs regroupées lors d'une rafale
    cs_reset($pdo);
    $t = cs_time('2026-11-10 10:00:00');
    VisitCollector::recordDropped($pdo, 'not_found', VisitCollector::SUBJECT_NOT_FOUND_BURST, 0, $t);
    $check('rafale : 0 demande ignorée n\'écrit rien', cs_count($pdo, 'errors') === 0);
    VisitCollector::recordDropped($pdo, 'not_found', VisitCollector::SUBJECT_NOT_FOUND_BURST, 3, $t);
    VisitCollector::recordDropped($pdo, 'not_found', VisitCollector::SUBJECT_NOT_FOUND_BURST, 2, $t + 60);
    VisitCollector::recordDropped($pdo, 'security', VisitCollector::SUBJECT_WRITE_CEILING, 4, $t);
    $check('rafale : une ligne par jour et par type, les demandes ignorées s\'additionnent', cs_count($pdo, 'errors') === 2 && cs_value($pdo, "SELECT occurrences FROM errors WHERE kind = 'not_found'") == 5 && cs_value($pdo, "SELECT occurrences FROM errors WHERE kind = 'security'") == 4);
    cs_reset($pdo);
    VisitCollector::recordDropped($pdo, 'not_found', "/x\x1B[31m" . str_repeat('a', 400), 1, $t);
    $subject = (string) cs_value($pdo, 'SELECT subject FROM errors');
    $check('rafale : un sujet venu de l\'extérieur est nettoyé et borné à 255 caractères', strlen($subject) === 255 && strpos($subject, "\x1B") === false);
    $check('rafale : les sujets réservés ne sont jamais un chemin', $pdo !== null && strpos(VisitCollector::SUBJECT_NOT_FOUND_BURST, '/') !== 0 && strpos(VisitCollector::SUBJECT_WRITE_CEILING, '/') !== 0);
    $check('rafale : sujets plus courts que la colonne', strlen(VisitCollector::SUBJECT_NOT_FOUND_BURST) < 255 && strlen(VisitCollector::SUBJECT_WRITE_CEILING) < 255);
}

/**
 * @param callable(string=):PDO $connect
 * @param callable(array,int):string $rec
 */
function cs_scenario_failures(PDO $pdo, callable $connect, callable $rec, callable $check): void
{
    $t0     = cs_time('2026-11-10 10:00:00');
    $secret = cs_secret();

    // --- une ligne créée par une autre requête entre la mise à jour et l'insertion : le doublon est absorbé
    cs_reset($pdo);
    $rec(['server' => ['HTTP_DNT' => '1']], $t0);
    $bad = $connect(CollectorTestPdo::class);
    $bad->blindUpdate('/^UPDATE daily_stats/', 1);
    $r = VisitCollector::record($bad, $secret, cs_request(['server' => ['HTTP_DNT' => '1']]), $t0 + 5);
    $check('doublon : compteur quotidien absorbé', $r === 'counted' && cs_count($pdo, 'daily_stats') === 1 && cs_value($pdo, 'SELECT hits FROM daily_stats') == 2);

    cs_reset($pdo);
    $notFound = ['page' => null, 'status' => 404, 'server' => ['REQUEST_URI' => '/x']];
    $rec($notFound, $t0);
    $bad = $connect(CollectorTestPdo::class);
    $bad->blindUpdate('/^UPDATE errors/', 1);
    VisitCollector::record($bad, $secret, cs_request($notFound), $t0 + 5);
    $check('doublon : erreur regroupée absorbée', cs_count($pdo, 'errors') === 1 && cs_value($pdo, 'SELECT occurrences FROM errors') == 2);

    // --- une erreur qui n'est PAS un doublon n'est jamais avalée
    $inserts = [
        'compteur du jour'  => ["/^INSERT INTO daily_stats/", cs_request()],
        'erreur regroupée'  => ["/^INSERT INTO errors/", cs_request(['page' => null, 'status' => 404, 'server' => ['REQUEST_URI' => '/x']])],
    ];
    foreach ($inserts as $label => [$pattern, $request]) {
        cs_reset($pdo);
        $bad = $connect(CollectorTestPdo::class);
        $bad->failOn($pattern, 1);
        $thrown = null;
        try {
            VisitCollector::record($bad, $secret, $request, $t0);
        } catch (PDOException $e) {
            $thrown = $e;
        }
        $check("erreur d'insertion ({$label}) : remonte et annule tout", $thrown !== null && cs_count($pdo, 'daily_stats') === 0 && cs_count($pdo, 'errors') === 0 && cs_count($pdo, 'visits') === 0);
    }

    cs_reset($pdo);
    $bad = $connect(CollectorTestPdo::class);
    $bad->failOn('/^INSERT INTO settings/', 1);
    $log = cs_log_begin();
    VisitCollector::maintain($bad, $t0);
    $text = cs_log_end($log);
    $check('réglage impossible à créer : journalisé, sans empêcher la purge', strpos($text, 'effacement des clés de visite impossible (PDO HY000/1105)') !== false && cs_value($pdo, "SELECT COUNT(*) FROM settings WHERE name = 'last_rotation'") == 0 && cs_value($pdo, "SELECT value FROM settings WHERE name = 'last_purge'") === '2026-11-10');

    // --- réglage créé par une autre requête entre la lecture et l'insertion : c'est elle qui s'en charge
    cs_reset($pdo);
    VisitCollector::rotate($pdo, $t0);
    $bad = $connect(CollectorTestPdo::class);
    $bad->blindUpdate('/^SELECT value FROM settings/', 1);
    $check('réglage créé entre-temps : doublon absorbé, rotation = false', VisitCollector::rotate($bad, $t0 + 5) === false && cs_count($pdo, 'settings') === 1);

    // --- interblocage : réessayé une fois, sans rien écrire deux fois
    cs_reset($pdo);
    $bad = $connect(CollectorTestPdo::class);
    $bad->failOn('/^UPDATE daily_stats/', 1, true);
    $r = VisitCollector::record($bad, $secret, cs_request(), $t0);
    $check('interblocage passager : l\'écriture réussit au deuxième essai', $r === 'visit_started' && $bad->failed === 1);
    $check('interblocage passager : une seule visite, un seul parcours, un seul passage', cs_count($pdo, 'visits') === 1 && cs_count($pdo, 'hits') === 1 && cs_value($pdo, 'SELECT hits FROM daily_stats') == 1);
    $check('interblocage passager : aucune transaction ouverte', $bad->inTransaction() === false);

    cs_reset($pdo);
    $bad = $connect(CollectorTestPdo::class);
    $bad->failOn('/^UPDATE daily_stats/', 5, true);
    $thrown = null;
    try {
        VisitCollector::record($bad, $secret, cs_request(), $t0);
    } catch (PDOException $e) {
        $thrown = $e;
    }
    $check('interblocage persistant : l\'erreur remonte après exactement 2 essais', $thrown !== null && VisitCollector::isDeadlock($thrown) && $bad->failed === 2);
    $check('interblocage persistant : rien n\'est écrit', cs_count($pdo, 'visits') === 0 && cs_count($pdo, 'hits') === 0 && cs_count($pdo, 'daily_stats') === 0 && $bad->inTransaction() === false);

    // --- erreur ordinaire : pas de nouvel essai, tout est annulé
    cs_reset($pdo);
    $bad = $connect(CollectorTestPdo::class);
    $bad->failOn('/^INSERT INTO hits/', 1);
    $thrown = null;
    try {
        VisitCollector::record($bad, $secret, cs_request(), $t0);
    } catch (PDOException $e) {
        $thrown = $e;
    }
    $check('erreur ordinaire : l\'erreur remonte, sans nouvel essai', $thrown !== null && !VisitCollector::isDeadlock($thrown) && $bad->failed === 1);
    $check('erreur ordinaire : tout est annulé, pas de visite sans parcours ni de compteur seul', cs_count($pdo, 'visits') === 0 && cs_count($pdo, 'hits') === 0 && cs_count($pdo, 'daily_stats') === 0 && $bad->inTransaction() === false);
    $check('erreur ordinaire : la connexion reste utilisable', VisitCollector::record($bad, $secret, cs_request(), $t0 + 1) === 'visit_started' && cs_count($pdo, 'visits') === 1);

    // --- transaction ouverte par l'appelant : respectée
    cs_reset($pdo);
    $pdo->beginTransaction();
    VisitCollector::record($pdo, $secret, cs_request(), $t0);
    $stillOpen = $pdo->inTransaction();
    $pdo->rollBack();
    $check('transaction de l\'appelant : ni validée ni fermée par la collecte', $stillOpen === true && cs_count($pdo, 'visits') === 0 && cs_count($pdo, 'daily_stats') === 0);
}

/**
 * Cohérence sur une longue série de requêtes (graine fixe) : un modèle écrit à part, sans SQL, prédit le nombre de
 * visites et de pages ; la base doit lui donner raison, et ses compteurs doivent rester cohérents entre eux.
 */
function cs_scenario_model(PDO $pdo, callable $check): void
{
    cs_reset($pdo);
    mt_srand(20261110);

    $secret   = cs_secret();
    $visitors = [];
    for ($i = 0; $i < 12; $i++) {
        $visitors[] = ['ip' => '198.51.100.' . (10 + $i), 'ua' => CS_CHROME . ' V' . $i];
    }
    $pages = ['home' => '/accueil/fr', 'career' => '/parcours/fr', 'creations' => '/creations/fr', 'contactUs' => '/contact/fr'];

    $now      = cs_time('2026-11-10 08:00:00');
    $total    = 400;
    $lastHit  = [];     // visiteur => dernier instant rattaché à une visite
    $expected = ['visits' => 0, 'hits' => 0, 'dnt' => 0];
    $perDay   = [];
    $failures = 0;

    for ($n = 0; $n < $total; $n++) {
        $now += mt_rand(0, 2400);
        $visitor = mt_rand(0, count($visitors) - 1);
        $v       = $visitors[$visitor];
        $roll    = mt_rand(1, 100);
        $dnt     = mt_rand(1, 100) <= 10;

        if ($roll <= 70) {
            [$status, $page, $uri] = [200, array_keys($pages)[mt_rand(0, 3)], null];
            $uri = $pages[$page];
        } elseif ($roll <= 85) {
            [$status, $page, $uri] = [404, null, '/inconnue-' . mt_rand(1, 6)];
        } elseif ($roll <= 95) {
            [$status, $page, $uri] = [301, 'home', '/accueil'];
        } else {
            [$status, $page, $uri] = [500, 'home', '/accueil/fr'];
        }

        $server = ['REMOTE_ADDR' => $v['ip'], 'HTTP_USER_AGENT' => $v['ua'], 'REQUEST_URI' => $uri];
        if ($dnt) {
            $server['HTTP_DNT'] = '1';
        }

        try {
            VisitCollector::record($pdo, $secret, cs_request(['server' => $server, 'page' => $page, 'status' => $status]), $now);
        } catch (Throwable $e) {
            $failures++;
            continue;
        }

        $day = VisitPrivacy::day($now);
        $perDay[$day] = ($perDay[$day] ?? 0) + 1;

        // Le modèle
        if ($dnt) {
            $expected['dnt']++;
            continue;
        }
        if ($status === 301) {
            continue;
        }
        if (VisitPrivacy::continues($lastHit[$visitor] ?? null, $now)) {
            $expected['hits']++;
            $lastHit[$visitor] = $now;
        } elseif ($status === 200) {
            $expected['visits']++;
            $expected['hits']++;
            $lastHit[$visitor] = $now;
        }
    }

    $check('série : aucune écriture en échec', $failures === 0);
    $check("série : {$expected['visits']} visites prédites par le modèle", cs_count($pdo, 'visits') === $expected['visits'] && $expected['visits'] > 20);
    $check("série : {$expected['hits']} pages de parcours prédites par le modèle", cs_count($pdo, 'hits') === $expected['hits']);
    $check('série : le compteur quotidien compte TOUTES les pages, jour par jour', (int) cs_value($pdo, 'SELECT SUM(hits) FROM daily_stats') === $total && count($perDay) >= 2);

    $byDay = [];
    foreach (cs_rows($pdo, 'SELECT stat_date, SUM(hits) AS n FROM daily_stats GROUP BY stat_date') as $row) {
        $byDay[$row['stat_date']] = (int) $row['n'];
    }
    ksort($byDay);
    ksort($perDay);
    $check('série : compteur de chaque jour exact', $byDay === $perDay);

    $inconsistent = (int) cs_value(
        $pdo,
        'SELECT COUNT(*) FROM visits v WHERE v.hit_count <> (SELECT COUNT(*) FROM hits h WHERE h.visit_id = v.visit_id)'
    );
    $check('série : le nombre de pages de chaque visite est celui de son parcours', $inconsistent === 0);
    $check('série : aucune visite sans parcours, aucun parcours sans visite', (int) cs_value($pdo, 'SELECT COUNT(*) FROM visits v WHERE NOT EXISTS (SELECT 1 FROM hits h WHERE h.visit_id = v.visit_id)') === 0 && (int) cs_value($pdo, 'SELECT COUNT(*) FROM hits h WHERE NOT EXISTS (SELECT 1 FROM visits v WHERE v.visit_id = h.visit_id)') === 0);
    $check('série : seules les visites des jours passés ont perdu leur clé', (int) cs_value($pdo, 'SELECT COUNT(*) FROM visits WHERE visit_key IS NULL AND visit_day >= \'' . VisitPrivacy::day($now) . '\'') === 0 && (int) cs_value($pdo, 'SELECT COUNT(*) FROM visits WHERE visit_key IS NOT NULL AND visit_day < \'' . VisitPrivacy::day($now) . '\'') === 0);
    $check('série : aucune adresse écrite', strpos(cs_dump($pdo), '198.51.100') === false);
}

// ----------------------------------------------------------------------
// CollectorHook
// ----------------------------------------------------------------------

/**
 * @param callable(string=):PDO $connect
 * @param callable(string,bool):void $check
 * @param string $workDir dossier de travail jetable (compteurs des freins, fichier du disjoncteur, configurations)
 */
function collectorHookScenarios(callable $connect, callable $check, string $workDir): void
{
    $pdo   = $connect();
    $now   = cs_time('2026-11-10 10:00:00');
    $calls = 0;
    $config = [
        'db_host' => '127.0.0.1', 'db_port' => 3306, 'db_name' => 'test', 'db_user' => 'test', 'db_password' => 'mot-de-passe-secret-42',
        'server_secret' => cs_secret(),
    ];
    $connector = static function (?PDO $to = null) use (&$calls, $pdo): callable {
        return static function (array $config) use (&$calls, $pdo, $to): ?PDO {
            $calls++;

            return $to ?? $pdo;
        };
    };
    $limiterDir = $workDir . DIRECTORY_SEPARATOR . 'limiter';
    @mkdir($limiterDir, 0700, true);
    $freshLimiter = static function () use ($limiterDir): BurstLimiter {
        foreach (glob($limiterDir . DIRECTORY_SEPARATOR . 'jt-dashboard-burst-*') ?: [] as $file) {
            @unlink($file);
        }

        return new BurstLimiter($limiterDir);
    };
    $cooldown = $workDir . DIRECTORY_SEPARATOR . 'cooldown';
    $options  = static function (array $more = []) use ($config, $connector, $freshLimiter, $now, $cooldown): array {
        $defaults = ['config' => $config, 'connect' => $connector(), 'now' => $now, 'cooldown_file' => $cooldown];
        if (!isset($more['limiter'])) {
            // Un limiteur neuf remet les compteurs à zéro : jamais quand le test en fournit un
            $defaults['limiter'] = $freshLimiter();
        }

        return $more + $defaults;
    };
    @unlink($cooldown);

    // --- chemin normal
    cs_reset($pdo);
    $check('hook : page 200 = visit_started', CollectorHook::handle(cs_context(), $options()) === 'visit_started');
    $check('hook : visite écrite', cs_count($pdo, 'visits') === 1 && cs_count($pdo, 'hits') === 1);
    $check('hook : une 404 réelle (code 404) = counted', CollectorHook::handle(cs_context(['http_code' => 404, 'page' => null, 'server' => ['REQUEST_URI' => '/inconnue', 'REMOTE_ADDR' => '198.51.100.1']]), $options()) === 'counted');

    cs_reset($pdo);
    $r = CollectorHook::handle(cs_context(['http_code' => 200, 'last_error' => ['type' => E_ERROR, 'message' => 'x', 'file' => 'f', 'line' => 1]]), $options());
    $check('hook : erreur fatale = 500 enregistré, erreur server_error', $r === 'counted' && cs_value($pdo, 'SELECT hits FROM daily_stats WHERE status = 500') == 1 && cs_count($pdo, 'errors') === 1);

    // --- requêtes non collectées : aucune connexion
    $calls = 0;
    cs_reset($pdo);
    $skipped = [
        CollectorHook::handle(cs_context(['server' => ['REQUEST_METHOD' => 'POST']]), $options()),
        CollectorHook::handle(cs_context(['server' => ['REQUEST_METHOD' => 'HEAD']]), $options()),
        CollectorHook::handle(cs_context(['http_code' => 302]), $options()),
        CollectorHook::handle(cs_context(['http_code' => 403]), $options()),
    ];
    $check('hook : POST, HEAD, 302 et 403 ignorés sans connexion', $skipped === ['skipped', 'skipped', 'skipped', 'skipped'] && $calls === 0 && cs_dump($pdo) === cs_dump_empty());

    // --- interrupteur général et configuration
    $previous = getenv(DashboardConfig::ENV_VARIABLE);
    $restore  = static function () use ($previous): void {
        putenv($previous === false ? DashboardConfig::ENV_VARIABLE : DashboardConfig::ENV_VARIABLE . '=' . $previous);
    };

    $calls = 0;
    putenv(DashboardConfig::ENV_VARIABLE . '=' . $workDir . DIRECTORY_SEPARATOR . 'absent.php');
    $r = CollectorHook::handle(cs_context(), ['connect' => $connector(), 'limiter' => $freshLimiter(), 'now' => $now, 'cooldown_file' => $cooldown]);
    $check('hook : pas de fichier de configuration = disabled, sans connexion, sans frein', $r === 'disabled' && $calls === 0);

    $badFile = $workDir . DIRECTORY_SEPARATOR . 'invalide.php';
    file_put_contents($badFile, "<?php return ['db_host' => 'localhost', 'db_password' => 'mot-de-passe-secret-42'];\n");
    putenv(DashboardConfig::ENV_VARIABLE . '=' . $badFile);
    $log = cs_log_begin();
    $r = CollectorHook::handle(cs_context(), ['connect' => $connector(), 'limiter' => $freshLimiter(), 'now' => $now, 'cooldown_file' => $cooldown]);
    $text = cs_log_end($log);
    $check('hook : configuration invalide = misconfigured, sans connexion', $r === 'misconfigured' && $calls === 0);
    $check('hook : configuration invalide journalisée sans le mot de passe', strpos($text, 'configuration inutilisable') !== false && strpos($text, 'mot-de-passe-secret-42') === false);

    $secretHex = 'a1b2c3d4e5f60718293a4b5c6d7e8f90a1b2c3d4e5f60718293a4b5c6d7e8f90';
    $goodFile  = $workDir . DIRECTORY_SEPARATOR . 'valide.php';
    file_put_contents($goodFile, "<?php return ['db_host' => 'localhost', 'db_name' => 'test', 'db_user' => 'test', 'db_password' => 'mot-de-passe-secret-42', 'secret' => '" . $secretHex . "'];\n");
    putenv(DashboardConfig::ENV_VARIABLE . '=' . $goodFile);
    cs_reset($pdo);
    $calls = 0;
    $log = cs_log_begin();
    $r = CollectorHook::handle(cs_context(), ['connect' => $connector(), 'limiter' => $freshLimiter(), 'now' => $now, 'cooldown_file' => $cooldown]);
    $text = cs_log_end($log);
    $expectedKey = VisitPrivacy::key(VisitPrivacy::dailySalt((string) hex2bin($secretHex), '2026-11-10'), CS_IP, CS_CHROME);
    $check('hook : configuration réelle chargée, une connexion', $r === 'visit_started' && $calls === 1);
    $check('hook : la clé de visite est calculée avec le secret du fichier', cs_value($pdo, 'SELECT visit_key FROM visits') === $expectedKey && $expectedKey !== null);
    $check('hook : rien n\'est journalisé quand tout va bien', $text === '');

    // --- sans l'option de configuration : le disjoncteur en cours empêche toute connexion
    touch($cooldown);
    putenv(DashboardConfig::ENV_VARIABLE . '=' . $goodFile);
    file_put_contents($goodFile, "<?php return ['db_host' => '127.0.0.1', 'db_port' => 1, 'db_name' => 'test', 'db_user' => 'test', 'db_password' => 'x', 'secret' => '" . $secretHex . "'];\n");
    $log = cs_log_begin();
    $r = CollectorHook::handle(cs_context(), ['limiter' => $freshLimiter(), 'now' => $now, 'cooldown_file' => $cooldown]);
    $text = cs_log_end($log);
    $check('hook : disjoncteur enclenché = unavailable, sans tentative de connexion ni journal', $r === 'unavailable' && $text === '');

    @unlink($cooldown);
    $log = cs_log_begin();
    $r = CollectorHook::handle(cs_context(), ['limiter' => $freshLimiter(), 'now' => $now, 'cooldown_file' => $cooldown]);
    $text = cs_log_end($log);
    $check('hook : base injoignable = unavailable, disjoncteur enclenché', $r === 'unavailable' && is_file($cooldown) && DashboardDatabase::isCoolingDown($cooldown));
    $check('hook : base injoignable journalisée sans identifiant ni adresse', strpos($text, 'base de données indisponible') !== false && strpos($text, CS_IP) === false && strpos($text, '127.0.0.1') === false);
    @unlink($cooldown);
    $restore();

    // --- base absente ou en erreur
    $calls = 0;
    $r = CollectorHook::handle(cs_context(), $options(['connect' => static function (array $c): ?PDO {
        return null;
    }]));
    $check('hook : connexion refusée = unavailable', $r === 'unavailable');

    cs_reset($pdo);
    $bad = $connect(CollectorTestPdo::class);
    $bad->failOn('/^UPDATE daily_stats/', 1);
    $log = cs_log_begin();
    $r = CollectorHook::handle(cs_context(['server' => ['REQUEST_URI' => '/page-secrete?jeton=abc']]), $options(['connect' => $connector($bad)]));
    $text = cs_log_end($log);
    $check('hook : écriture impossible = failed', $r === 'failed' && cs_count($pdo, 'visits') === 0 && cs_count($pdo, 'daily_stats') === 0);
    $check('hook : un problème durable enclenche le disjoncteur', is_file($cooldown) && DashboardDatabase::isCoolingDown($cooldown));
    $check('hook : l\'échec est journalisé sans donnée du visiteur', strpos($text, 'écriture impossible (PDO HY000/1105)') !== false && strpos($text, CS_IP) === false && strpos($text, 'page-secrete') === false && strpos($text, 'jeton') === false && strpos($text, 'Chrome') === false);
    @unlink($cooldown);

    $bad = $connect(CollectorTestPdo::class);
    $bad->failOn('/^UPDATE daily_stats/', 9, true);
    $log = cs_log_begin();
    $r = CollectorHook::handle(cs_context(), $options(['connect' => $connector($bad)]));
    cs_log_end($log);
    $check('hook : un interblocage persistant échoue sans enclencher le disjoncteur', $r === 'failed' && !is_file($cooldown));

    // --- freins
    cs_reset($pdo);
    $limiter = $freshLimiter();
    $calls   = 0;
    $results = [];
    for ($i = 1; $i <= 32; $i++) {
        $results[] = CollectorHook::handle(cs_context(['http_code' => 404, 'page' => null, 'server' => ['REQUEST_URI' => '/scan-' . $i]]), $options(['limiter' => $limiter]));
    }
    $check('frein : 30 premières 404 écrites, les suivantes ignorées', array_slice($results, 0, 30) === array_fill(0, 30, 'counted') && array_slice($results, 30) === ['limited', 'limited']);
    $check('frein : les demandes ignorées n\'ouvrent aucune connexion', $calls === 30 && cs_count($pdo, 'errors') === 30);

    $r = CollectorHook::handle(cs_context(['server' => ['REMOTE_ADDR' => '198.51.100.77']]), $options(['limiter' => $limiter]));
    $check('frein : une page normale n\'est pas freinée par la rafale de 404', $r === 'visit_started');
    $check('frein : le résumé de la rafale est écrit dès qu\'une écriture passe (2 ignorées)', cs_value($pdo, "SELECT occurrences FROM errors WHERE subject = '" . VisitCollector::SUBJECT_NOT_FOUND_BURST . "'") == 2);

    $r = CollectorHook::handle(cs_context(['http_code' => 404, 'page' => null, 'server' => ['REQUEST_URI' => '/scan-fenetre-suivante']]), $options(['limiter' => $limiter, 'now' => $now + 61]));
    $check('frein : nouvelle fenêtre, les 404 repassent', $r === 'counted');

    cs_reset($pdo);
    $limiter = $freshLimiter();
    for ($i = 0; $i < CollectorHook::LIMIT_WRITES; $i++) {
        $limiter->allow('writes', CollectorHook::LIMIT_WRITES, CollectorHook::LIMIT_WINDOW_SECONDS, $now);
    }
    $calls = 0;
    $r1 = CollectorHook::handle(cs_context(), $options(['limiter' => $limiter]));
    $r2 = CollectorHook::handle(cs_context(['server' => ['HTTP_DNT' => '1']]), $options(['limiter' => $limiter]));
    $check('frein : plafond global de 600 écritures par minute', $r1 === 'limited' && $r2 === 'limited' && $calls === 0 && cs_dump($pdo) === cs_dump_empty());
    $r = CollectorHook::handle(cs_context(), $options(['limiter' => $limiter, 'now' => $now + 61]));
    $check('frein : à la fenêtre suivante la collecte reprend et résume les demandes ignorées', $r === 'visit_started' && cs_value($pdo, "SELECT occurrences FROM errors WHERE kind = 'security'") == 2);

    // --- le point d'entrée en ligne de commande
    $property = new ReflectionProperty(CollectorHook::class, 'registered');
    if (PHP_VERSION_ID < 80100) {
        $property->setAccessible(true);
    }
    CollectorHook::register();
    $check('register : sans effet en ligne de commande', $property->getValue() === false);

    putenv(DashboardConfig::ENV_VARIABLE . '=' . $workDir . DIRECTORY_SEPARATOR . 'absent.php');
    ob_start();
    $threw = false;
    try {
        CollectorHook::shutdown();
    } catch (Throwable $e) {
        $threw = true;
    }
    $output = (string) ob_get_clean();
    $restore();
    $check('shutdown : ne lève jamais d\'exception et n\'écrit rien dans la page', $threw === false && $output === '');
}
