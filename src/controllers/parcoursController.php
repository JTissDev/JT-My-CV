<?php
// --- Début de ton contrôleur existant ---
define('CLASSDIR', __DIR__ . '/../../classes');
require_once CLASSDIR . '/EvenementParcours.php';
require_once CLASSDIR . '/Experience.php';
require_once CLASSDIR . '/Etude.php';
require_once CLASSDIR . '/ParcoursManager.php';

$currentLang = isset($lang) ? $lang : 'fr';
$parcoursItems = ParcoursManager::getParcoursItems($currentLang);
// --- Fin de l'existant ---

// NOUVEAU : Préparation des données formatées pour la Vue
$formattedRows = [];

foreach ($parcoursItems as $item) {
    // 1. Définition des variables d'affichage par défaut
    $displayData = [
        'item'         => $item, // On conserve l'objet original
        'typeClass'    => '',
        'typeLabel'    => '',
        'locationInfo' => '',
        'sideClass'    => ''
    ];

    // 2. La logique de présentation est maintenant ici, dans le contrôleur
    if ($item instanceof Experience) {
    $displayData['typeClass'] = 'timeline-item--experience';
    
    // Appel dynamique au libellé JSON (avec fallback sur 'work' ou 'experience')
    $displayData['typeLabel'] = $data->pages->career->card_title->work 
        ?? $data->pages->career->card_title->experience 
        ?? 'Expérience';
        
    $displayData['locationInfo'] = $item->getEntreprise() . ' — ' . $item->getVille() . ' (' . $item->getDept() . ')';
    $displayData['sideClass'] = 'timeline-item--left';

} elseif ($item instanceof Etude) {
    $displayData['typeClass'] = 'timeline-item--etude';
    
    // Appel dynamique pour les études
    $displayData['typeLabel'] = $data->pages->career->card_title->study 
        ?? 'Étude / Formation';
        
    $displayData['locationInfo'] = $item->getEtablissement() . ' — ' . $item->getVille() . ' (' . $item->getDept() . ')';
    $displayData['sideClass'] = 'timeline-item--right';

} else {
    $displayData['typeClass'] = 'timeline-item--projet';
    
    // Appel dynamique pour les projets
    $displayData['typeLabel'] = $data->pages->career->card_title->project 
        ?? 'Projet';
        
    $displayData['locationInfo'] = $item->getVille() . ' (' . $item->getDept() . ')';
    $displayData['sideClass'] = 'timeline-item--right';
}

    // 3. Regroupement intelligent dans les lignes
    if ($item->hasLinkedGroup()) {
        $groupKey = 'group_' . $item->getLinkedGroupId();
        $formattedRows[$groupKey][] = $displayData;
    } else {
        $groupKey = 'single_' . $item->getId();
        $formattedRows[$groupKey][] = $displayData;
    }
}

// À la fin de ce fichier, tu appelles ta vue (ex: include 'pages/parcours.php';)