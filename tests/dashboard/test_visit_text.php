<?php
declare(strict_types=1);

/**
 * Test de classes/dashboard/VisitText.php : nettoyage des textes avant écriture en base.
 * Exécution : php tests/dashboard/test_visit_text.php   (ligne de commande uniquement ; aucune base requise)
 *
 * Le contrôle principal est une propriété : QUELLE QUE SOIT l'entrée (octets aléatoires, UTF-8 invalide, caractères de
 * contrôle, textes énormes), la sortie est de l'UTF-8 valide, sans caractère de contrôle, d'au plus 255 caractères.
 * C'est ce que la base en mode strict exige pour ne jamais refuser une écriture.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../../classes/dashboard/VisitText.php';

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

/** Nombre de caractères UTF-8 (sans mbstring). */
function chars(string $text): int
{
    return (int) preg_match_all('/./su', $text);
}

/** Vrai si le texte est exploitable tel quel dans une colonne VARCHAR(255) utf8mb4 en mode strict. */
function storable(string $text): bool
{
    return preg_match('//u', $text) === 1
        && preg_match('/[\p{Cc}\p{Cf}\p{Zl}\p{Zp}]/u', $text) !== 1
        && chars($text) <= 255;
}

// --- clean : cas simples
check('texte ordinaire inchangé',                 VisitText::clean('/mon-parcours/fr') === '/mon-parcours/fr');
check('accents conservés',                         VisitText::clean('/café/été') === '/café/été');
check('emoji (4 octets) conservé',                 VisitText::clean('/a🙂b') === '/a🙂b');
check('texte vide',                                VisitText::clean('') === '');
check('caractères de contrôle ASCII retirés',      VisitText::clean("a\x00b\x1Fc\x7Fd") === 'abcd');
check('retours à la ligne et tabulations retirés', VisitText::clean("a\r\nb\tc") === 'abc');
check('contrôle C1 (U+0085) retiré',               VisitText::clean("a\u{0085}b") === 'ab');
check('inverseur de sens d\'écriture retiré',      VisitText::clean("a\u{202E}b") === 'ab');
check('espace de largeur nulle retiré',            VisitText::clean("a\u{200B}b") === 'ab');
check('séparateur de ligne U+2028 retiré',         VisitText::clean("a\u{2028}b") === 'ab');
check('espaces de bord rognés',                    VisitText::clean('  /a  ') === '/a');

// --- clean : UTF-8 invalide
check('octet isolé > 0x7F -> « ? »',               VisitText::clean("caf\xE9") === 'caf?');
check('octets 0xFF 0xFE -> « ?? »',                VisitText::clean("ok\xFF\xFE") === 'ok??');
check('séquence tronquée -> « ? »',                VisitText::clean("a\xE2\x82") === 'a??');
check('suite ASCII conservée autour',              VisitText::clean("/a\xC0/b") === '/a?/b');
check('résultat valide en UTF-8',                  preg_match('//u', VisitText::clean("\x80\x81\xFF")) === 1);

// --- clean : longueur en caractères
check('255 caractères : inchangé',                 VisitText::clean(str_repeat('a', 255)) === str_repeat('a', 255));
check('256 caractères : coupé à 255',              chars(VisitText::clean(str_repeat('a', 256))) === 255);
check('300 « é » : 255 caractères',                chars(VisitText::clean(str_repeat('é', 300))) === 255);
check('300 emoji : 255 caractères, valides',       chars(VisitText::clean(str_repeat('🙂', 300))) === 255 && preg_match('//u', VisitText::clean(str_repeat('🙂', 300))) === 1);
check('limite personnalisée',                      VisitText::clean('abcdef', 3) === 'abc');
check('limite 1',                                  VisitText::clean('abcdef', 1) === 'a');
check('limite 0 : vide',                           VisitText::clean('abcdef', 0) === '');
check('espace en fin de coupe rogné',              VisitText::clean('ab cd', 3) === 'ab');

// --- clean : entrées énormes (bornées à 4096 octets avant tout traitement)
$start = microtime(true);
$big = VisitText::clean(str_repeat('a', 1_000_000));
check('1 Mo de « a » : 255 caractères',            $big === str_repeat('a', 255));
check('1 Mo de « é » : valide, 255 caractères',    storable(VisitText::clean(str_repeat('é', 500_000))) && chars(VisitText::clean(str_repeat('é', 500_000))) === 255);
check('1 Mo d\'octets 0xFF : valide',              storable(VisitText::clean(str_repeat("\xFF", 1_000_000))));
check('coupe au milieu d\'un « € » (3 octets)',    VisitText::clean(str_repeat('€', 3000)) === str_repeat('€', 255));
check('coupe au milieu d\'un emoji (4 octets)',    VisitText::clean(str_repeat('a', 4095) . '🙂🙂') === str_repeat('a', 255));
check('entrées énormes traitées en moins de 2 s',  (microtime(true) - $start) < 2.0);

// --- clean : propriété sur des octets aléatoires
mt_srand(20261107);
$bad = 0;
for ($i = 0; $i < 3000; $i++) {
    $length = mt_rand(0, 700);
    $raw = '';
    for ($j = 0; $j < $length; $j++) {
        $raw .= chr(mt_rand(0, 255));
    }
    if (!storable(VisitText::clean($raw))) {
        $bad++;
    }
}
check('3000 chaînes d\'octets aléatoires : toujours stockables', $bad === 0);

$bad = 0;
$alphabet = ["\x00", "\n", "é", "€", "🙂", "\u{202E}", "\u{2028}", "\xC3", "\xE2\x82", "a", "/", "?", " ", "\x7F", "\u{0085}"];
for ($i = 0; $i < 3000; $i++) {
    $raw = '';
    for ($j = mt_rand(0, 400); $j > 0; $j--) {
        $raw .= $alphabet[mt_rand(0, count($alphabet) - 1)];
    }
    if (!storable(VisitText::clean($raw))) {
        $bad++;
    }
}
check('3000 mélanges de caractères délicats : toujours stockables', $bad === 0);

$all = '';
for ($b = 0; $b < 256; $b++) {
    $all .= chr($b);
}
check('les 256 valeurs d\'octet : stockable',      storable(VisitText::clean($all)));
check('jamais d\'octet nul en sortie',             strpos(VisitText::clean("a\x00b"), "\x00") === false);

// --- path : requête et fragment retirés
check('requête retirée',                           VisitText::path('/a/b?x=1') === '/a/b');
check('fragment retiré',                           VisitText::path('/a/b#frag') === '/a/b');
check('requête et fragment retirés',               VisitText::path('/a/b?x=1#y') === '/a/b');
check('« ? » après « # » : tout est retiré',       VisitText::path('/a#frag?x=1') === '/a');
check('requête seule : « / »',                     VisitText::path('?only=query') === '/');
check('vide : « / »',                              VisitText::path('') === '/');
check('« / » seul',                                VisitText::path('/') === '/');
check('« * » (OPTIONS) : « /* »',                  VisitText::path('*') === '/*');
check('« / » ajouté devant',                       VisitText::path('abc') === '/abc');
check('contrôles de tête retirés',                 VisitText::path("\x00/abc") === '/abc');
check('uniquement des contrôles : « / »',          VisitText::path("\x00\x01\x02") === '/');
check('jeton de visite guidée jamais conservé',    strpos(VisitText::path('/accueil/fr?guide=JETON-SECRET-123'), 'JETON') === false);
check('chemin avec %00 gardé tel quel (texte)',    VisitText::path('/a%00b') === '/a%00b');
check('chemin de 1000 caractères : 255',           chars(VisitText::path('/' . str_repeat('x', 999))) === 255);
check('chemin d\'une 404 de scanner',              VisitText::path('/wp-login.php?redirect_to=http://x') === '/wp-login.php');
check('chemin : toujours stockable (octets invalides)', storable(VisitText::path("/a\xFF\xFE/b?c=\xFF")));
check('chemin : commence par « / » dans tous les cas', VisitText::path("\x01\x02abc")[0] === '/');

// --- userAgent
check('User-Agent borné à 255 caractères',         chars(VisitText::userAgent(str_repeat('M', 600))) === 255);
check('User-Agent : contrôles retirés',            VisitText::userAgent("curl/8\r\nX-Injected: 1") === 'curl/8X-Injected: 1');
check('User-Agent : stockable (octets invalides)', storable(VisitText::userAgent("Bot\xFF/1.0\x00")));

echo "\nRésultat : {$ok} OK, {$ko} échec(s), 0 section(s) ignorée(s)\n";
exit($ko === 0 ? 0 : 1);
