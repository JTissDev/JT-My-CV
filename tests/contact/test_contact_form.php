<?php
declare(strict_types=1);

/**
 * Fichier : tests/contact/test_contact_form.php
 * Rôle : Contrôle les fonctions du formulaire de contact qui remplacent la session (src/helpers/contact-form.php) :
 *   - jeton du formulaire : signé, daté, refusé s'il est falsifié, trop récent (< 3 s), trop ancien (> 6 h) ou d'une autre finalité ;
 *   - jeton de confirmation d'envoi (?sent=...) : valable 2 minutes ;
 *   - contrôle de l'origine de la requête (Sec-Fetch-Site, Origin, Host) ;
 *   - nettoyage des saisies et champ piège (non-régression) ;
 *   - cohérence avec le reste du projet : plus aucune trace de session dans le code public, message d'erreur des
 *     fichiers de langue sans le mot « session », nom du champ attendu par le test de fumée de deploy.yml.
 * Lancement (depuis la racine du projet) : php tests/contact/test_contact_form.php
 * Aucune base de données, aucun serveur web, aucun e-mail.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('APP_STARTED', true);

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

function skipSection(string $label): void
{
    global $skipped;
    $skipped++;
    echo "IGNORÉ : {$label}\n";
}

function finish(): void
{
    global $ok, $ko, $skipped;
    echo "\nRésultat : {$ok} OK, {$ko} échec(s), {$skipped} section(s) ignorée(s)\n";
    exit($ko === 0 ? 0 : 1);
}

set_exception_handler(static function (Throwable $e): void {
    global $ko;
    $ko++;
    echo 'ÉCHEC : exception imprévue (' . get_class($e) . ') : ' . $e->getMessage() . "\n";
    finish();
});

$root = dirname(__DIR__, 2);
$helper = $root . '/src/helpers/contact-form.php';
if (!is_file($helper)) {
    echo "ÉCHEC : src/helpers/contact-form.php introuvable (lancer le test depuis la racine du projet)\n";
    $ko++;
    finish();
}
require_once $helper;

// ============================================================================
// 1. Constantes
// ============================================================================
check('champ du jeton = « csrf_token » (nom cherché par le test de fumée de deploy.yml)', CONTACT_TOKEN_FIELD === 'csrf_token');
check('paramètre de confirmation = « sent »', CONTACT_SENT_PARAM === 'sent');
check('âge minimal du jeton : 3 s', CONTACT_FORM_TOKEN_MIN_AGE === 3);
check('âge maximal du jeton : 6 h', CONTACT_FORM_TOKEN_MAX_AGE === 6 * 3600);
check('confirmation valable 2 min', CONTACT_SENT_TOKEN_MAX_AGE === 120);
check('plus de constante de clé de session', !defined('CONTACT_CSRF_SESSION_KEY') && !defined('CONTACT_FLASH_SESSION_KEY'));
check('plus de fonction liée à la session', !function_exists('contactCsrfToken') && !function_exists('contactCsrfIsValid') && !function_exists('startAppSession'));

// ============================================================================
// 2. Jeton du formulaire
// ============================================================================
$t0 = 1_800_000_000;
$token = contactFormToken($t0);
check('forme du jeton : « <date>.<64 caractères hexadécimaux> »', preg_match('/^1800000000\.[0-9a-f]{64}\z/', $token) === 1);
check('même date : même jeton (déterministe)', contactFormToken($t0) === $token);
check('autre date : autre jeton', contactFormToken($t0 + 1) !== $token);
check('jeton sans date fournie : utilise l\'heure courante', contactFormTokenIsValid(contactFormToken(time() - 10)));

foreach ([
    0 => false, 1 => false, 2 => false,
    3 => true, 4 => true, 60 => true, 3600 => true, 21600 => true,
    21601 => false, 86400 => false,
] as $age => $expected) {
    check("jeton du formulaire âgé de {$age} s : " . ($expected ? 'accepté' : 'refusé'), contactFormTokenIsValid($token, $t0 + $age) === $expected);
}
check('jeton daté du futur (horloge reculée) : refusé', contactFormTokenIsValid($token, $t0 - 1) === false);

// Falsifications
$dot = strpos($token, '.');
$signature = substr($token, $dot + 1);
$flippedChar = $signature[63] === '0' ? '1' : '0';
$bad = [
    'dernier caractère de la signature changé'     => substr($token, 0, -1) . $flippedChar,
    'date changée, signature d\'origine'          => '1800000001.' . $signature,
    'date retranchée (rajeunir le jeton)'          => '1799999000.' . $signature,
    'signature en majuscules'                      => '1800000000.' . strtoupper($signature),
    'signature tronquée'                           => substr($token, 0, -2),
    'signature allongée'                           => $token . '0',
    'sans point'                                   => str_replace('.', '', $token),
    'date vide'                                    => '.' . $signature,
    'date de 13 chiffres'                          => '1800000000000.' . $signature,
    'espace en tête'                               => ' ' . $token,
    'espace à la fin'                              => $token . ' ',
    'retour à la ligne à la fin'                   => $token . "\n",
    'octet nul'                                    => $token . "\0",
    'chaîne vide'                                  => '',
    'texte quelconque'                             => 'abc',
    'signe négatif'                                => '-' . $token,
];
foreach ($bad as $label => $value) {
    check("jeton refusé : {$label}", contactFormTokenIsValid($value, $t0 + 10) === false);
}
foreach ([null, 0, 123, 1.5, true, false, [], [$token], new stdClass()] as $i => $value) {
    check('valeur qui n\'est pas une chaîne (cas ' . $i . ') : refusée sans erreur', contactFormTokenIsValid($value, $t0 + 10) === false);
}

// Une finalité ne sert pas pour l'autre
$sent = contactSentToken($t0);
check('le jeton de confirmation n\'est pas accepté comme jeton de formulaire', contactFormTokenIsValid($sent, $t0 + 10) === false);
check('le jeton de formulaire n\'est pas accepté comme confirmation d\'envoi', contactSentTokenIsValid($token, $t0 + 10) === false);
check('les deux jetons de la même seconde sont différents', $sent !== $token);

// ============================================================================
// 3. Confirmation d'envoi
// ============================================================================
foreach ([0 => true, 1 => true, 60 => true, 120 => true, 121 => false, 3600 => false] as $age => $expected) {
    check("confirmation âgée de {$age} s : " . ($expected ? 'acceptée' : 'refusée'), contactSentTokenIsValid($sent, $t0 + $age) === $expected);
}
check('confirmation datée du futur : refusée', contactSentTokenIsValid($sent, $t0 - 1) === false);
check('confirmation falsifiée : refusée', contactSentTokenIsValid('1800000000.' . str_repeat('a', 64), $t0 + 1) === false);
check('confirmation « 1 » (ancien format) : refusée', contactSentTokenIsValid('1', $t0 + 1) === false);
check('confirmation absente (null) : refusée', contactSentTokenIsValid(null, $t0 + 1) === false);
check('confirmation en tableau (?sent[]=x) : refusée sans erreur', contactSentTokenIsValid(['x'], $t0 + 1) === false);
check('confirmation sans date fournie : utilise l\'heure courante', contactSentTokenIsValid(contactSentToken(time())));

// ============================================================================
// 4. Origine de la requête
// ============================================================================
$host = 'www.jtissier-developpeur-informatique.fr';
$cases = [
    // [libellé, $_SERVER, attendu]
    ['ni Sec-Fetch-Site ni Origin (curl, très ancien navigateur)', ['HTTP_HOST' => $host], true],
    ['Sec-Fetch-Site: same-origin', ['HTTP_HOST' => $host, 'HTTP_SEC_FETCH_SITE' => 'same-origin'], true],
    ['Sec-Fetch-Site: SAME-ORIGIN (casse)', ['HTTP_HOST' => $host, 'HTTP_SEC_FETCH_SITE' => 'SAME-ORIGIN'], true],
    ['Sec-Fetch-Site: cross-site', ['HTTP_HOST' => $host, 'HTTP_SEC_FETCH_SITE' => 'cross-site'], false],
    ['Sec-Fetch-Site: same-site (autre sous-domaine)', ['HTTP_HOST' => $host, 'HTTP_SEC_FETCH_SITE' => 'same-site'], false],
    ['Sec-Fetch-Site: none', ['HTTP_HOST' => $host, 'HTTP_SEC_FETCH_SITE' => 'none'], false],
    ['Sec-Fetch-Site: same-origin l\'emporte sur un Origin étranger', ['HTTP_HOST' => $host, 'HTTP_SEC_FETCH_SITE' => 'same-origin', 'HTTP_ORIGIN' => 'https://evil.example'], true],
    ['Sec-Fetch-Site: cross-site l\'emporte sur un Origin conforme', ['HTTP_HOST' => $host, 'HTTP_SEC_FETCH_SITE' => 'cross-site', 'HTTP_ORIGIN' => 'https://' . $host], false],
    ['Sec-Fetch-Site vide : on lit Origin (conforme)', ['HTTP_HOST' => $host, 'HTTP_SEC_FETCH_SITE' => '', 'HTTP_ORIGIN' => 'https://' . $host], true],
    ['Origin identique à Host', ['HTTP_HOST' => $host, 'HTTP_ORIGIN' => 'https://' . $host], true],
    ['Origin identique à Host, casse différente', ['HTTP_HOST' => $host, 'HTTP_ORIGIN' => 'HTTPS://WWW.JTISSIER-developpeur-informatique.FR'], true],
    ['Origin en http et Host sans schéma (le schéma n\'est pas comparé)', ['HTTP_HOST' => $host, 'HTTP_ORIGIN' => 'http://' . $host], true],
    ['Origin d\'un autre site', ['HTTP_HOST' => $host, 'HTTP_ORIGIN' => 'https://evil.example'], false],
    ['Origin : autre sous-domaine', ['HTTP_HOST' => $host, 'HTTP_ORIGIN' => 'https://blog.jtissier-developpeur-informatique.fr'], false],
    ['Origin : domaine qui contient le nôtre', ['HTTP_HOST' => $host, 'HTTP_ORIGIN' => 'https://' . $host . '.evil.example'], false],
    ['Origin : notre domaine en identifiant (…@evil)', ['HTTP_HOST' => $host, 'HTTP_ORIGIN' => 'https://' . $host . '@evil.example'], false],
    ['Origin : null (document isolé)', ['HTTP_HOST' => $host, 'HTTP_ORIGIN' => 'null'], false],
    ['Origin vide', ['HTTP_HOST' => $host, 'HTTP_ORIGIN' => ''], false],
    ['Origin sans schéma', ['HTTP_HOST' => $host, 'HTTP_ORIGIN' => $host], false],
    ['Origin avec un schéma qui n\'est pas http(s)', ['HTTP_HOST' => $host, 'HTTP_ORIGIN' => 'ftp://' . $host], false],
    ['Origin mal formé', ['HTTP_HOST' => $host, 'HTTP_ORIGIN' => 'https:///'], false],
    ['Origin présent mais Host absent', ['HTTP_ORIGIN' => 'https://' . $host], false],
    ['Origin présent mais Host vide', ['HTTP_HOST' => '', 'HTTP_ORIGIN' => 'https://' . $host], false],
    ['Origin qui n\'est pas une chaîne', ['HTTP_HOST' => $host, 'HTTP_ORIGIN' => ['https://' . $host]], false],
    ['localhost : mêmes ports', ['HTTP_HOST' => 'localhost:8080', 'HTTP_ORIGIN' => 'http://localhost:8080'], true],
    ['localhost : ports différents', ['HTTP_HOST' => 'localhost:8080', 'HTTP_ORIGIN' => 'http://localhost:3000'], false],
    ['localhost : port écrit d\'un seul côté (non comparé)', ['HTTP_HOST' => 'localhost', 'HTTP_ORIGIN' => 'http://localhost:80'], true],
    ['adresse IPv6 identique', ['HTTP_HOST' => '[::1]:8080', 'HTTP_ORIGIN' => 'http://[::1]:8080'], true],
    ['adresse IPv6 différente', ['HTTP_HOST' => '[::1]:8080', 'HTTP_ORIGIN' => 'http://[::2]:8080'], false],
    ['adresse IPv4 du réseau local', ['HTTP_HOST' => '192.168.1.20', 'HTTP_ORIGIN' => 'http://192.168.1.20'], true],
];
foreach ($cases as [$label, $server, $expected]) {
    check("origine : {$label} -> " . ($expected ? 'acceptée' : 'refusée'), contactRequestIsSameOrigin($server) === $expected);
}

// ============================================================================
// 5. Saisies et champ piège (non-régression)
// ============================================================================
check('contactSingleLine : retours à la ligne et espaces répétés réduits', contactSingleLine("  Jean \r\n  Dupont\t ") === 'Jean Dupont');
check('contactSingleLine : tableau refusé', contactSingleLine(['x']) === '');
check('contactSingleLine : UTF-8 invalide refusé', contactSingleLine("a\xFFb") === '');
check('contactMultiLine : sauts de ligne conservés (LF)', contactMultiLine("a\r\nb\rc") === "a\nb\nc");
check('contactMultiLine : caractères de contrôle retirés', contactMultiLine("a\x01b") === 'ab');
check('contactLength : compte des caractères, pas des octets', contactLength('éé') === 2);
check('contactEscape : guillemets échappés', contactEscape('"\'<') === '&quot;&#039;&lt;');
check('champ piège vide : pas déclenché', contactHoneypotTriggered([CONTACT_HONEYPOT_FIELD => '']) === false);
check('champ piège absent : pas déclenché', contactHoneypotTriggered([]) === false);
check('champ piège « 0 » : déclenché', contactHoneypotTriggered([CONTACT_HONEYPOT_FIELD => '0']) === true);
check('champ piège en tableau : déclenché', contactHoneypotTriggered([CONTACT_HONEYPOT_FIELD => ['x']]) === true);

// ============================================================================
// 6. Cohérence avec le reste du projet
// ============================================================================
$read = static function (string $relative) use ($root): ?string {
    $path = $root . '/' . $relative;

    return is_file($path) ? (string) file_get_contents($path) : null;
};

// Code public : aucune trace de session ni de cookie
$sessionPattern = '/session_start\s*\(|session_name\s*\(|session_id\s*\(|session_regenerate_id\s*\(|setcookie\s*\(|setrawcookie\s*\(|\$_SESSION|\$_COOKIE|startAppSession|set-cookie/i';
$scanned = 0;
foreach (['index.php', 'src', 'templates', 'print', 'api', 'classes'] as $entry) {
    $path = $root . '/' . $entry;
    if (is_file($path)) {
        $files = [$path];
    } elseif (is_dir($path)) {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
    } else {
        continue;
    }
    foreach ($files as $file) {
        $scanned++;
        $relative = ltrim(str_replace('\\', '/', substr($file, strlen($root))), '/');
        check("{$relative} : ni session ni cookie", preg_match($sessionPattern, (string) file_get_contents($file)) !== 1);
    }
}
check('au moins un fichier PHP public contrôlé', $scanned > 0);

// Messages d'erreur : plus de « session » dans csrf_error (il n'y a plus de session)
foreach (['fr', 'en'] as $lang) {
    $raw = $read("data/cv_{$lang}.json");
    if ($raw === null) {
        skipSection("data/cv_{$lang}.json introuvable : message csrf_error non contrôlé");
        continue;
    }
    $json = json_decode($raw);
    $message = $json->meta->forms_errors->csrf_error ?? null;
    check("data/cv_{$lang}.json : csrf_error est un texte non vide", is_string($message) && trim($message) !== '');
    check("data/cv_{$lang}.json : csrf_error ne parle plus de session", is_string($message) && stripos($message, 'session') === false);
}

// Le test de fumée de deploy.yml cherche le champ du jeton dans la page de contact
$deploy = $read('.github/workflows/deploy.yml');
if ($deploy === null) {
    skipSection('.github/workflows/deploy.yml introuvable : nom du champ non rapproché du test de fumée');
} else {
    check('deploy.yml : le test de fumée cherche name="' . CONTACT_TOKEN_FIELD . '"', strpos($deploy, 'name="' . CONTACT_TOKEN_FIELD . '"') !== false);
}

// La vue écrit bien le champ du jeton et le champ piège
$view = $read('templates/pages/contactUs.php');
if ($view === null) {
    skipSection('templates/pages/contactUs.php introuvable');
} else {
    check('vue : le champ du jeton utilise CONTACT_TOKEN_FIELD', strpos($view, 'name="<?= CONTACT_TOKEN_FIELD ?>"') !== false);
    check('vue : le champ piège est présent', strpos($view, 'CONTACT_HONEYPOT_FIELD') !== false);
}

finish();
