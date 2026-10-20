<?php
/**
 * Rôle : Préparation et sécurisation des données pour l'en-tête.
 */
if (!defined('APP_STARTED')) {
    die('Accès direct interdit');
}


// 1. Isolation et extraction des données brutes
$headerData = (isset($data) && isset($data->header)) ? $data->header : null;

$headerName  = $headerData->name ?? 'Julien Tissier';

$headerTitle = $headerData->title ?? 'Développeur Web';

// 2. Conversion et réorganisation de la navigation
$navItems = ($headerData && isset($headerData->nav)) ? (array) $headerData->nav : [];

if (array_key_exists('home', $navItems)) {
    $homeLabel = $navItems['home'];
    unset($navItems['home']);

    $middleIndex = (int) ceil(count($navItems) / 2);

    $leftPart  = array_slice($navItems, 0, $middleIndex, true);
    $rightPart = array_slice($navItems, $middleIndex, null, true);

    $navItems = $leftPart + ['home' => $homeLabel] + $rightPart;
}

// 3. Paramètres de routage et de langue
$currentPage    = $page ?? 'home';
$currentLang    = $lang ?? 'fr';
$targetLang     = ($currentLang === 'en') ? 'fr' : 'en';
$langButtonText = ($currentLang === 'en') ? 'FR' : 'EN';

// Génération de l'URL propre vers la page de contact selon la langue active
$contactUrl = getCleanUrl('contactUs', $lang);

// Libellé traduit (ex: "Me contacter" en FR, "Contact Me" en EN)
$contactLabel = ($lang === 'en') ? 'Contact Me' : 'Me contacter';

// Instanciation de notre composant LinkButton avec une classe spécifique pour le header
$contactButton = new LinkButton($contactLabel, $contactUrl, 'btn-header-contact');