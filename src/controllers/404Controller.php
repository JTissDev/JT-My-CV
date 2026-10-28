<?php
/**
 * Fichier : src/controllers/404Controller.php
 * Rôle : Préparation des données pour la page d'erreur 404.
 *
 * Le code HTTP 404 et l'en-tête X-Robots-Tag sont envoyés par index.php, avant tout affichage.
 */

if (!defined('APP_STARTED')) {
    exit('Direct access not permitted');
}

// Langue : celle choisie par le routeur, 'fr' par défaut
$currentLang = (isset($lang) && in_array($lang, ['fr', 'en'], true)) ? $lang : 'fr';

// Textes de secours (au cas où pages.error404 manquerait dans le fichier de langue)
$errorFallbacks = [
    'fr' => [
        'code'       => '404',
        'title'      => 'Page introuvable',
        'subtitle'   => 'Erreur 404',
        'message'    => 'La page demandée n\'existe pas.',
        'buttonText' => 'Retour à l\'accueil',
    ],
    'en' => [
        'code'       => '404',
        'title'      => 'Page not found',
        'subtitle'   => 'Error 404',
        'message'    => 'The requested page does not exist.',
        'buttonText' => 'Back to home',
    ],
];
$errorFallback = $errorFallbacks[$currentLang];

// Récupération sécurisée de l'objet d'erreur depuis nos données JSON
$errorData = (isset($data) && isset($data->pages->error404)) ? $data->pages->error404 : null;

$code       = $errorData->code       ?? $errorFallback['code'];
$title      = $errorData->title      ?? $errorFallback['title'];
$subtitle   = $errorData->subtitle   ?? $errorFallback['subtitle'];
$message    = $errorData->message    ?? $errorFallback['message'];
$buttonText = $errorData->buttonText ?? $errorFallback['buttonText'];

// Bouton de retour : adresse propre de l'accueil dans la langue active
$homeUrl = getCleanUrl('home', $currentLang);
