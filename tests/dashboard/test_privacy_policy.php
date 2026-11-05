<?php
declare(strict_types=1);

/**
 * Fichier : tests/dashboard/test_privacy_policy.php
 * Rôle : Contrôle la politique de confidentialité (pages.confidentialite de data/cv_fr.json et data/cv_en.json) :
 *   - structure attendue par la vue (titre, sections, sous-titres, paragraphes en texte brut) ;
 *   - parallélisme FR / EN (même nombre de sections et de paragraphes, même date de mise à jour) ;
 *   - balises {…} résolues exactement comme le fait index.php (registre puis données de langue) ;
 *   - chiffres et engagements alignés sur docs/dashboard-vie-privee.md (durées, minuit, DNT, cookies) ;
 *   - cohérence avec ce que le site fait réellement : tant que G5 et G6 ne sont pas livrés, le texte ne doit pas
 *     parler de cookies jt_dev / jt_guest (à retirer de la liste $notYetLive en livrant G5 et G6).
 * Lancement (depuis la racine du projet) : php tests/dashboard/test_privacy_policy.php
 * Ne dit rien de la valeur juridique du texte (voir B9) : il empêche seulement qu'il se désaccorde du code et de la spec.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

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

/** Lit un fichier JSON comme index.php (objets, sans BOM toléré) ; null si absent ou invalide. */
function loadJson(string $path): ?stdClass
{
    if (!is_file($path)) {
        return null;
    }
    try {
        $value = json_decode((string) file_get_contents($path), false, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        return null;
    }

    return $value instanceof stdClass ? $value : null;
}

/** Tous les paragraphes d'une politique : [[sous-titre, [paragraphes]], ...] ; null si la structure est invalide. */
function policyShape(mixed $policy): ?array
{
    if (!$policy instanceof stdClass || !isset($policy->title, $policy->sections) || !is_string($policy->title) || !is_array($policy->sections)) {
        return null;
    }
    $shape = [];
    foreach ($policy->sections as $section) {
        if (!$section instanceof stdClass || !isset($section->subtitle, $section->content) || !is_string($section->subtitle) || !is_array($section->content)) {
            return null;
        }
        foreach ($section->content as $paragraph) {
            if (!is_string($paragraph)) {
                return null;
            }
        }
        $shape[] = [$section->subtitle, $section->content];
    }

    return $shape;
}

$root = dirname(__DIR__, 2);
$langs = ['fr', 'en'];

// ============================================================================
// 1. Fichiers et JSON
// ============================================================================
$registreRaw = loadJson($root . '/data/registre.json');
$raw = [];
foreach ($langs as $lang) {
    $raw[$lang] = loadJson($root . "/data/cv_{$lang}.json");
}
check('data/registre.json : JSON valide (objet)', $registreRaw !== null && isset($registreRaw->meta));
foreach ($langs as $lang) {
    check("data/cv_{$lang}.json : JSON valide (objet)", $raw[$lang] !== null);
}
check('src/helpers/registry-helpers.php présent', is_file($root . '/src/helpers/registry-helpers.php'));
if ($ko > 0) {
    echo "Fichiers de données illisibles : les contrôles suivants sont impossibles.\n";
    finish();
}

// Même chaîne de traitement que index.php : le registre s'interpole avec son propre meta, puis les données de langue
define('PRINT_WANTED', true); // autorise l'inclusion de registry-helpers.php (garde de ce fichier)
require_once $root . '/src/helpers/registry-helpers.php';
check('interpolateRegistry() disponible', function_exists('interpolateRegistry'));
if (!function_exists('interpolateRegistry')) {
    finish();
}
$registre = interpolateRegistry($registreRaw, $registreRaw->meta);
$data = [];
foreach ($langs as $lang) {
    $data[$lang] = interpolateRegistry($raw[$lang], $registre->meta);
}

// ============================================================================
// 2. Structure
// ============================================================================
$shape = [];
foreach ($langs as $lang) {
    $policy = $data[$lang]->pages->confidentialite ?? null;
    $shape[$lang] = policyShape($policy);
    check("{$lang} : pages.confidentialite a la structure attendue (title, sections[subtitle, content[]])", $shape[$lang] !== null);
}
if ($shape['fr'] === null || $shape['en'] === null) {
    finish();
}
check('fr : titre « Politique de Confidentialité »', $data['fr']->pages->confidentialite->title === 'Politique de Confidentialité');
check('en : titre « Privacy Policy »', $data['en']->pages->confidentialite->title === 'Privacy Policy');

foreach ($langs as $lang) {
    $sectionNumber = 0;
    foreach ($shape[$lang] as [$subtitle, $paragraphs]) {
        $sectionNumber++;
        check("{$lang} : section {$sectionNumber} a un sous-titre", trim($subtitle) !== '');
        check("{$lang} : section {$sectionNumber} a au moins un paragraphe", count($paragraphs) > 0);
        foreach ($paragraphs as $index => $paragraph) {
            $label = "{$lang} : section {$sectionNumber}, paragraphe " . ($index + 1);
            check("{$label} non vide", trim($paragraph) !== '');
            check("{$label} en texte brut (la vue échappe le HTML : ni < ni >)", strpbrk($paragraph, '<>') === false);
            check("{$label} de taille raisonnable (900 caractères au plus)", mb_strlen($paragraph) <= 900);
            check("{$label} sans balise non résolue ni marque de brouillon", preg_match('/\{[A-Za-z0-9_-]+\}|TODO|XXX|À COMPLÉTER|TBD/u', $paragraph) !== 1);
        }
    }
}

// ============================================================================
// 3. Parallélisme FR / EN
// ============================================================================
check('FR et EN ont le même nombre de sections', count($shape['fr']) === count($shape['en']));
foreach ($shape['fr'] as $i => [$subtitle, $paragraphs]) {
    $other = $shape['en'][$i][1] ?? [];
    check('section ' . ($i + 1) . ' : même nombre de paragraphes en FR et en EN (' . count($paragraphs) . ')', count($paragraphs) === count($other));
}

// ============================================================================
// 4. Balises et valeurs du registre
// ============================================================================
$text = [];
foreach ($langs as $lang) {
    $parts = [];
    foreach ($shape[$lang] as [$subtitle, $paragraphs]) {
        $parts[] = $subtitle;
        foreach ($paragraphs as $paragraph) {
            $parts[] = $paragraph;
        }
    }
    $text[$lang] = implode("\n", $parts);
}
$meta = $registre->meta;
foreach (['name', 'site_url', 'dev_mail', 'dev_adress', 'host_name'] as $key) {
    $value = $meta->$key ?? null;
    check("registre.meta.{$key} est une chaîne non vide", is_string($value) && trim($value) !== '');
    if (is_string($value) && trim($value) !== '') {
        foreach ($langs as $lang) {
            check("{$lang} : la valeur de « {$key} » apparaît dans le texte", strpos($text[$lang], $value) !== false);
        }
    }
}
check('registre.meta.dev_adress est entièrement résolue (aucune balise restante)', preg_match('/\{[^}]*\}/', (string) ($meta->dev_adress ?? '')) !== 1);

// Les adresses de la page doivent mener à la bonne vue : le slug de chaque langue renvoie vers « confidentialite »
$slugMap = (array) ($meta->slugs ?? new stdClass());
foreach ($langs as $lang) {
    $slug = $data[$lang]->meta->pages->confidentialite->name ?? null;
    check("{$lang} : slug de la page (« " . (is_string($slug) ? $slug : '?') . ' ») connu du registre et mène à « confidentialite »', is_string($slug) && ($slugMap[$slug] ?? null) === 'confidentialite');
}

// ============================================================================
// 5. Chiffres et engagements alignés sur la spec (docs/dashboard-vie-privee.md)
// ============================================================================
$mustContain = [
    'fr' => ['13 mois', '90 jours', '12 mois', '7 jours', '30 minutes', 'minuit', '32 caractères', 'aucun cookie', 'Ne pas me suivre',
        'Do Not Track', 'Global Privacy Control', 'jamais votre adresse IP', 'RGPD', 'CNIL', 'utm_source', 'utm_medium', 'utm_campaign'],
    'en' => ['13 months', '90 days', '12 months', '7 days', '30 minutes', 'midnight', '32-character', 'no cookie', 'Do Not Track',
        'Global Privacy Control', 'never records your IP address', 'GDPR', 'CNIL', 'utm_source', 'utm_medium', 'utm_campaign'],
];
foreach ($langs as $lang) {
    foreach ($mustContain[$lang] as $needle) {
        check("{$lang} : le texte contient « {$needle} »", strpos($text[$lang], $needle) !== false);
    }
}

// Ce qui n'existe pas encore ne doit pas être décrit (et le texte promet « aucun cookie »)
$notYetLive = ['jt_dev', 'jt_guest', 'PHPSESSID'];
foreach ($langs as $lang) {
    foreach ($notYetLive as $word) {
        check("{$lang} : le texte ne parle pas de « {$word} » (fonction pas encore livrée)", stripos($text[$lang], $word) === false);
    }
}

// Date de mise à jour : présente dans les deux langues, et c'est la même date
$months = [
    'fr' => ['janvier' => 1, 'février' => 2, 'mars' => 3, 'avril' => 4, 'mai' => 5, 'juin' => 6, 'juillet' => 7, 'août' => 8,
        'septembre' => 9, 'octobre' => 10, 'novembre' => 11, 'décembre' => 12],
    'en' => ['January' => 1, 'February' => 2, 'March' => 3, 'April' => 4, 'May' => 5, 'June' => 6, 'July' => 7, 'August' => 8,
        'September' => 9, 'October' => 10, 'November' => 11, 'December' => 12],
];
$patterns = ['fr' => '/Dernière mise à jour : (\d{1,2}) (\p{L}+) (20\d{2})\./u', 'en' => '/Last updated: (\d{1,2}) (\p{L}+) (20\d{2})\./u'];
$dates = [];
foreach ($langs as $lang) {
    $found = preg_match($patterns[$lang], $text[$lang], $m) === 1;
    check("{$lang} : date de dernière mise à jour présente et lisible", $found);
    $dates[$lang] = null;
    if ($found && isset($months[$lang][$m[2]]) && checkdate($months[$lang][$m[2]], (int) $m[1], (int) $m[3])) {
        $dates[$lang] = sprintf('%04d-%02d-%02d', (int) $m[3], $months[$lang][$m[2]], (int) $m[1]);
    }
    check("{$lang} : la date de mise à jour est une date réelle", $dates[$lang] !== null);
}
check('FR et EN portent la même date de mise à jour', $dates['fr'] !== null && $dates['fr'] === $dates['en']);

// La spec et le texte disent la même chose (la spec n'existe qu'en local : docs/ n'est pas déployé)
$specFile = $root . '/docs/dashboard-vie-privee.md';
if (is_file($specFile)) {
    $spec = (string) file_get_contents($specFile);
    foreach (['13 mois', '90 jours', '30 minutes', 'minuit', 'DNT'] as $needle) {
        check("docs/dashboard-vie-privee.md contient aussi « {$needle} »", strpos($spec, $needle) !== false);
    }
} else {
    skipSection('docs/dashboard-vie-privee.md introuvable : alignement texte / spec non contrôlé');
}

finish();
