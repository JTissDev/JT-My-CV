<?php
if (!defined('APP_STARTED')) {
    exit('Direct access not permitted');
}

require_once __DIR__ . '/../../classes/careerClasses/CareerManager.php';

$projectRoot = dirname(__DIR__, 2);
$careerManager = new CareerManager(
    $careerRegistryPath ?? $projectRoot . '/data/registre.json',
    $careerDataDirectory ?? $projectRoot . '/data',
    $data instanceof stdClass ? $data : null,
    $registre instanceof stdClass ? $registre : null
);
$careerTimeline = $careerManager->getItems($lang ?? 'fr');

$careerPage = $data->pages->career ?? new stdClass();
$careerPageTitle = $careerPage->page_title ?? 'Mon Parcours';
$careerEmptyMessage = $careerPage->empty_message ?? 'Aucune étape de parcours à afficher.';
$careerDetailsLabel = $careerPage->details_label ?? 'En savoir plus';
$careerTypeLabels = [
    'experience' => $careerPage->card_title->work ?? 'Expérience',
    'etude' => $careerPage->card_title->study ?? 'Étude / formation',
    'projet' => $careerPage->card_title->project ?? 'Projet',
    'alternance' => 'Alternance',
];
$careerPartialDirectory = dirname(__DIR__, 2) . '/templates/components/career';

$formattedRows = [];
foreach ($careerTimeline as $item) {
    $type = $item->getType();
    $safeType = preg_replace('/[^a-z0-9_-]/i', '', $type);
    $classes = ['timeline-item--' . $safeType];
    $hasItLink = false;
    $locationInfo = '';
    $facets = [];
    $partialName = 'item-event.php';

    if ($item instanceof TransitionParcours) {
        $partialName = 'item-transition.php';
    } elseif ($item instanceof Alternance) {
        $partialName = 'item-alternance.php';
        foreach ($item->getItems() as $member) {
            $hasItLink = $hasItLink || $member->hasLienInfo();
            $memberKind = $member instanceof Etude
                ? 'formation'
                : ($member instanceof Experience ? 'entreprise' : 'autre');
            if ($member instanceof Etude) {
                $memberHeading = $member->getEtablissement() ?: $member->getTitre();
            } elseif ($member instanceof Experience) {
                $memberHeading = $member->getEntreprise() ?: $member->getTitre();
            } else {
                $memberHeading = $member->getTitre();
            }
            $facets[] = [
                'kind' => $memberKind,
                'heading' => $memberHeading,
                'title' => $member->getTitre(),
                'place' => implode(', ', array_filter([$member->getVille(), $member->getDept()])),
                'description' => $member->getDescription(),
                'details' => $member->hasLongDescription() ? ($member->getLongDescription() ?? []) : null,
                'detailsId' => 'career-detail-' . preg_replace('/[^a-z0-9_-]/i', '-', $member->getId()),
            ];
        }
    } elseif ($item instanceof EvenementParcours) {
        $hasItLink = $item->hasLienInfo();
        $place = trim(implode(', ', array_filter([$item->getVille(), $item->getDept()])));
        $organization = '';
        if ($item instanceof Experience && $item->getEntreprise() !== '') {
            $organization = $item->getEntreprise();
        } elseif ($item instanceof Etude && $item->getEtablissement() !== '') {
            $organization = $item->getEtablissement();
        }
        $locationInfo = implode(' · ', array_filter([$organization, $place]));
    }

    if ($hasItLink) {
        $classes[] = 'has-it-link';
    }
    $dateStart = $item->getDateDebut();
    $dateEnd = $item->getDateFin();

    $formattedRows[] = [
        'partialPath' => $careerPartialDirectory . '/' . $partialName,
        'type' => $type,
        'typeLabel' => (string) ($careerTypeLabels[$type] ?? ''),
        'classes' => implode(' ', $classes),
        'dateLabel' => $dateStart . ($dateEnd !== '' ? ' – ' . $dateEnd : ''),
        'title' => $item->getTitre(),
        'description' => $item instanceof TransitionParcours ? $item->getDescription() : ($item instanceof EvenementParcours ? $item->getDescription() : []),
        'details' => $item instanceof EvenementParcours && $item->hasLongDescription() ? ($item->getLongDescription() ?? []) : null,
        'detailsId' => 'career-detail-' . preg_replace('/[^a-z0-9_-]/i', '-', $item->getId()),
        'locationInfo' => $locationInfo,
        'facets' => $facets,
        'detailsLabel' => $careerDetailsLabel,
    ];
}
