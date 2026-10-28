<?php
/**
 * Fichier : src/controllers/creationsController.php
 * Rôle : Contrôleur de la section "Mes Créations".
 * Respect strict MVC : préparation 100% en amont pour la grille et la vue détaillée.
 */

if (!defined('APP_STARTED')) {
    die('Accès direct interdit');
}

// 1. Récupération des projets techniques depuis le registre global
$rawProjects = isset($registre->projects) ? (array)$registre->projects : [];

// 2. Récupération des textes localisés depuis le fichier de langue actif
$localizedProjects = [];
if (isset($data->pages->creations->projects)) {
    $localizedProjects = (array)$data->pages->creations->projects;
}

// 3. Extraction et résolution en cascade des variables meta pour l'interpolation
$metaVars = [];
if (isset($registre->meta)) {
    foreach ($registre->meta as $k => $v) {
        if (is_string($v)) {
            $metaVars[$k] = $v;
        }
    }
}
for ($i = 0; $i < 2; $i++) {
    foreach ($metaVars as $k => &$v) {
        foreach ($metaVars as $sk => $sv) {
            $v = str_replace('{' . $sk . '}', $sv, $v);
        }
    }
    unset($v);
}

// 4. Fusion et interpolation des données de chaque projet
$creations = [];
foreach ($rawProjects as $project) {
    $projId = is_object($project) ? ($project->id ?? null) : ($project['id'] ?? null);
    if (!$projId) {
        continue;
    }

    $techData = is_object($project) ? (array)$project : $project;
    $textData = isset($localizedProjects[$projId]) ? (array)$localizedProjects[$projId] : [];

    $merged = array_merge($techData, $textData);

    foreach ($merged as $fieldKey => $fieldVal) {
        if (is_string($fieldVal)) {
            foreach ($metaVars as $mKey => $mVal) {
                $fieldVal = str_replace('{' . $mKey . '}', $mVal, $fieldVal);
            }
            $merged[$fieldKey] = $fieldVal;
        }
    }

    $creations[] = $merged;
}

// 5. Gestion de la vue détaillée (?creation=...) ou de la grille globale
// ?creation doit être un identifiant texte. Un tableau (?creation[]=x) ou un identifiant inconnu
// n'est pas une page : on le signale à index.php ($routeNotFound), qui répond alors par une vraie 404.
$selectedCreationId = null;
$currentCreation = null;

if (isset($_GET['creation'])) {
    if (!is_string($_GET['creation'])) {
        $routeNotFound = true;
        return;
    }

    $selectedCreationId = trim($_GET['creation']);
}

if ($selectedCreationId !== null && $selectedCreationId !== '') {
    foreach ($creations as $creation) {
        if (($creation['id'] ?? '') === $selectedCreationId) {
            $currentCreation = $creation;
            break;
        }
    }

    if ($currentCreation === null) {
        $routeNotFound = true;
        return;
    }
}

$pageStrings = $data->pages->creations ?? null;
$baseCreationsUrl = getCleanUrl('creations', $lang);

if ($currentCreation) {
    // --- MODE FICHE DÉTAILLÉE ---
    if (isset($currentCreation['title'])) {
        $meta->pageTitle = $currentCreation['title'] . ' - ' . ($pageStrings->title ?? 'Mes Créations');
    }

    $rawLongDesc = $currentCreation['longDescription'] ?? [];
    $projectParagraphs = is_array($rawLongDesc) ? $rawLongDesc : [$rawLongDesc];

    $projectBackUrl  = $baseCreationsUrl;
    $projectTitle    = $currentCreation['title'] ?? '';
    $projectCategory = $currentCreation['category'] ?? '';
    $projectYear     = $currentCreation['year'] ?? '';
    $projectImage    = $currentCreation['image'] ?? '';
    $projectTags     = is_array($currentCreation['tags'] ?? []) ? $currentCreation['tags'] : [];
    $projectGithub   = $currentCreation['githubUrl'] ?? '';
    $projectDemo     = $currentCreation['demoUrl'] ?? '';
    $lblDesc         = $pageStrings->labels->long_description ?? "Description détaillée";
    $lblTech         = $pageStrings->labels->technologies ?? "Technologies & Tags";
    $btnGithubText   = $pageStrings->buttons->github ?? "Voir sur GitHub";
    $btnDemoText     = $pageStrings->buttons->demo ?? "Démo Live";

    $viewPath = __DIR__ . '/../../templates/pages/creation-detail.php';
} else {
    // --- MODE GRILLE GLOBALE ---
    $creationsHeaderTitle    = $pageStrings->header_title ?? "Mes Créations & Réalisations";
    $creationsHeaderSubtitle = $pageStrings->header_subtitle ?? "Découvrez une sélection de mes projets techniques.";
    $creationsFilters        = $pageStrings->filters ?? (object)['all' => 'Tous', 'devops' => 'DevOps / Bash', 'web' => 'Web'];
    $btnDetailsText          = $pageStrings->buttons->details ?? "Voir le projet →";
    $noCreationsText         = "Aucune création pour le moment.";

    // Préparation des cartes de projets (zéro logique / zero fallback dans la vue)
    $preparedCreations = [];
    foreach ($creations as $creation) {
        $projId = $creation['id'] ?? '';
        $preparedCreations[] = [
            'category'         => $creation['category'] ?? '',
            'year'             => $creation['year'] ?? '',
            'title'            => $creation['title'] ?? '',
            'shortDescription' => $creation['shortDescription'] ?? '',
            'image'            => $creation['image'] ?? '',
            'tags'             => is_array($creation['tags'] ?? []) ? $creation['tags'] : [],
            'detailUrl'        => $baseCreationsUrl . '?creation=' . urlencode($projId)
        ];
    }

    $viewPath = __DIR__ . '/../../templates/pages/creations.php';
}
