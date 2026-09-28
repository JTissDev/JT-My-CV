<?php

/**
 * Fichier : pages/parcours.php
 * Rôle : Affichage de la frise chronologique du parcours (expériences, études, projets).
 */

// 1. Inclusion sécurisée des classes nécessaires
// On utilise __DIR__ pour être sûr que PHP trouve le dossier 'classes' 
define('CLASSDIR', __DIR__ . '/../../classes');
// peu importe d'où est lancé le script.
require_once CLASSDIR . '/EvenementParcours.php';
require_once CLASSDIR . '/Experience.php'; // Si tu as une classe Experience
require_once CLASSDIR . '/Etude.php';      // Si tu as une classe Etude
require_once CLASSDIR . '/ParcoursManager.php';
$currentLang = isset($lang) ? $lang : 'fr';


// Récupération des objets parcours via le manager
$parcoursItems = ParcoursManager::getParcoursItems($currentLang);
