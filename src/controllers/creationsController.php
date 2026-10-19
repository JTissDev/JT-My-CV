<?php
/**
 * Fichier : src/controllers/creationsController.php
 * Rôle : Contrôleur de la section "Mes Créations" (gestion isolée et autonome).
 */

if (!defined('APP_STARTED')) {
    die('Accès direct interdit');
}

// 1. Récupération des projets techniques depuis le registre global
$rawProjects = isset($registre->projects) ? (array)$registre->projects : [];

// 2. Récupération des textes localisés depuis le fichier de langue
$localizedProjects = [];
if (isset($data->pages->creations->projects)) {
    $localizedProjects = (array)$data->pages->creations->projects;
}

// 3. Extraction locale des variables du meta global pour les interpoler dans nos projets
$metaVars = [];
if (isset($registre->meta)) {
    foreach ($registre->meta as $k => $v) {
        if (is_string($v)) {
            $metaVars[$k] = $v;
        }
    }
}
// Résolution en cascade locale des variables meta (ex: shell-gif-folder utilisant images-folder)
for ($i = 0; $i < 2; $i++) {
    foreach ($metaVars as $k => &$v) {
        foreach ($metaVars as $sk => $sv) {
            $v = str_replace('{' . $sk . '}', $sv, $v);
        }
    }
    unset($v);
}

// 4. Fusion des données et interpolation locale pour chaque projet de cette page uniquement
$creations = [];
foreach ($rawProjects as $project) {
    $projId = is_object($project) ? ($project->id ?? null) : ($project['id'] ?? null);
    if (!$projId) {
        continue;
    }

    $techData = is_object($project) ? (array)$project : $project;
    $textData = isset($localizedProjects[$projId]) ? (array)$localizedProjects[$projId] : [];

    $merged = array_merge($techData, $textData);

    // Interpolation locale des balises (ex: {shell-gif-folder}/sitemap_manager-demo.gif)
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

// 5. Gestion de la vue détaillée (?creation=...)
$selectedCreationId = isset($_GET['creation']) ? trim($_GET['creation']) : null;
$currentCreation = null;

if ($selectedCreationId) {
    foreach ($creations as $creation) {
        if (($creation['id'] ?? '') === $selectedCreationId) {
            $currentCreation = $creation;
            break;
        }
    }
}

if ($currentCreation) {
    if (isset($currentCreation['title'])) {
        $meta->pageTitle = $currentCreation['title'] . ' - ' . ($data->pages->creations->title ?? 'Mes Créations');
    }
    $viewPath = __DIR__ . '/../../templates/pages/creation-detail.php';
} else {
    $viewPath = __DIR__ . '/../../templates/pages/creations.php';
}