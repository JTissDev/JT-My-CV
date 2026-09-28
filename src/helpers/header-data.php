<?php
/**
 * Rôle : Préparation et sécurisation des données pour l'en-tête.
 */

// 1. Isolation et extraction des données brutes
$headerData = (isset($data) && isset($data->header)) ? $data->header : null;

$name  = $headerData->name ?? 'Julien Tissier';
$title = $headerData->title ?? 'Développeur Web';

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