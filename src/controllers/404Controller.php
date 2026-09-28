<?php
/**
 * Fichier : src/Controllers/404Controller.php
 * Rôle : Préparation des données pour la page d'erreur 404.
 */

// Sécurisation de la variable langue : 
// Si $lang n'est pas transmise, on bascule sur 'fr' par défaut
$currentLang = isset($lang) ? $lang : 'fr';

// Récupération sécurisée de l'objet d'erreur depuis nos données JSON[cite: 9]
$errorData = (isset($data) && isset($data->pages->error404)) ? $data->pages->error404 : null;

// Valeurs de secours (fallbacks) au cas où le JSON serait indisponible[cite: 9]
$code       = $errorData ? $errorData->code : '404';
$title      = $errorData ? $errorData->title : 'Page introuvable';
$subtitle   = $errorData ? $errorData->subtitle : 'Erreur 404';
$message    = $errorData ? $errorData->message : 'La page demandée n\'existe pas.';
$buttonText = $errorData ? $errorData->buttonText : 'Retour à l\'accueil';