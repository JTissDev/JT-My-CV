<?php
declare(strict_types=1);

/**
 * Fichier : tests/test_career_render.php
 * Rôle : vérifie le chargement du parcours, ses pivots datés à un instant et le rendu des cartes.
 * Lancement (depuis la racine du projet) : php tests/test_career_render.php
 * Aucune base de données, aucun serveur web, aucun fichier du projet modifié.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
$required = [
    'classes/careerClasses/CareerManager.php',
    'data/registre.json',
    'data/cv_fr.json',
    'src/controllers/careerController.php',
    'templates/pages/career.php',
    'templates/components/career/item-event.php',
    'templates/components/career/item-alternance.php',
    'templates/components/career/item-transition.php',
];
$missing = array_values(array_filter($required, static fn (string $path): bool => !is_file($root . '/' . $path)));
if ($missing !== []) {
    fwrite(STDERR, "ÉCHEC : arborescence carrière incomplète : " . implode(', ', $missing) . "\n");
    exit(2);
}

$ok = 0;
$ko = 0;
function check(string $label, bool $condition): void
{
    global $ok, $ko;
    if ($condition) {
        $ok++;
    } else {
        $ko++;
        echo "ÉCHEC : {$label}\n";
    }
}

set_exception_handler(static function (Throwable $exception): void {
    global $ok, $ko;
    $ko++;
    echo 'ÉCHEC : exception imprévue (' . get_class($exception) . ') : ' . $exception->getMessage() . "\n";
    echo "\nRésultat : {$ok} OK, {$ko} échec(s)\n";
    exit(1);
});

$registre = json_decode((string) file_get_contents($root . '/data/registre.json'), false, 512, JSON_THROW_ON_ERROR);
$data = json_decode((string) file_get_contents($root . '/data/cv_fr.json'), false, 512, JSON_THROW_ON_ERROR);
$careerRegistryPath = $root . '/data/registre.json';
$careerDataDirectory = $root . '/data';
$lang = 'fr';
$e = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
define('APP_STARTED', true);
require $root . '/src/controllers/careerController.php';

check('chaque élément de la chronologie a sa ligne préparée', count($careerTimeline) === count($formattedRows));
$alternances = array_values(array_filter($careerTimeline, static fn ($item): bool => $item instanceof Alternance));
$transitions = array_values(array_filter($careerTimeline, static fn ($item): bool => $item instanceof TransitionParcours));
check('un groupe d’alternance est formé', count($alternances) === 1);
check('le groupe d’alternance contient la formation et l’expérience', count($alternances) === 1 && count($alternances[0]->getItems()) === 2);
check('les dates de l’alternance couvrent 2011–2013', count($alternances) === 1 && $alternances[0]->getDateDebut() === '2011' && $alternances[0]->getDateFin() === '2013');
check('les cinq transitions traduites sont incluses', count($transitions) === 5);

$expectedTransitions = [
    'maths' => ['bac_scientifique', '2007'],
    'bts_muc' => ['manutentionnaire_ldc', '2010'],
    'marches' => ['auto_entrepreneur_primeur', '2016'],
    'gf' => ['vendeur_gondolier_grand_frais', '2017'],
    'info' => ['titre_dev_web', '2019'],
];
$byTransitionId = [];
foreach ($transitions as $transition) {
    $byTransitionId[$transition->getId()] = $transition;
}
foreach ($expectedTransitions as $id => [$targetId, $date]) {
    $transition = $byTransitionId[$id] ?? null;
    check("transition {$id} liée à l’étape cible et à sa date unique {$date}",
        $transition instanceof TransitionParcours
        && $transition->getToId() === $targetId
        && $transition->getDateDebut() === $date
        && $transition->getDateFin() === '');
}
check('les descriptions longues non vides sont disponibles', count(array_filter($careerTimeline, static fn ($item): bool => $item instanceof EvenementParcours && $item->hasLongDescription())) > 0);
check('au moins une étape porte le marqueur lienInfo', count(array_filter($careerTimeline, static fn ($item): bool => $item instanceof EvenementParcours && $item->hasLienInfo())) > 0);

ob_start();
require $root . '/templates/pages/career.php';
$html = (string) ob_get_clean();
check('rendu de la carte alternance', str_contains($html, 'timeline-item--alternance'));
check('rendu des cartes transition', str_contains($html, 'timeline-item--transition'));
check('lienInfo est rendu comme classe visuelle, sans badge de texte', str_contains($html, 'has-it-link') && !str_contains($html, 'timeline-item__tech-badge'));
check('rendu des détails longs accessibles', str_contains($html, '<details class="timeline-item__details">'));
check('chaque élément est une ligne de liste sémantique', substr_count($html, '<li class="timeline-item ') === count($careerTimeline));
check('aucun badge ou libellé technique parasite n’est rendu',
    !str_contains($html, 'Repère informatique') && !str_contains($html, 'Informatique / Tech') && !str_contains($html, 'timeline-item__tech-badge'));
check('aucun libellé générique de transition ni eyebrow parasite n’est rendu',
    !str_contains($html, '>Transition<') && !str_contains($html, 'Virage de parcours') && !str_contains($html, 'timeline-item__eyebrow'));
check('la date affichée pour chaque pivot ne contient qu’une année',
    preg_match_all('/<li class="timeline-item [^\"]*timeline-item--transition[^\"]*">.*?<span class="timeline-item__date">(.*?)<\/span>/s', $html, $dateMatches) === 5
    && count(array_filter($dateMatches[1], static fn (string $date): bool => preg_match('/^\d{4}$/', trim(strip_tags($date))) !== 1)) === 0);
check('les trois vues sont incluses uniquement dans templates/components/career',
    count(array_filter(['item-event.php', 'item-alternance.php', 'item-transition.php'], static fn (string $file): bool => is_file($root . '/templates/components/career/' . $file))) === 3);
foreach (['item-event.php', 'item-alternance.php', 'item-transition.php'] as $partial) {
    $source = (string) file_get_contents($root . '/templates/components/career/' . $partial);
    check("{$partial} bloque l’accès direct", str_contains($source, "if (!defined('APP_STARTED'))"));
}
$viewSource = (string) file_get_contents($root . '/templates/pages/career.php');
check('la vue ne possède pas de fallback de composants à plat', !str_contains($viewSource, "__DIR__ . '/' . $partialName"));

printf("\nRésultat : %d OK, %d échec(s)\n", $ok, $ko);
exit($ko === 0 ? 0 : 1);
