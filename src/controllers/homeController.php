<?php
/**
 * Fichier : pages/home.php
 * Rôle : Affichage de la page d'accueil (Introduction détaillée et Compétences).
 */

$homeData = (isset($data) && isset($data->pages->home)) ? $data->pages->home : null;

// Variables générales
$greeting = ($homeData && isset($homeData->hero->greeting)) ? $homeData->hero->greeting : 'Bonjour';
$ctaText  = ($homeData && isset($homeData->hero->cta)) ? $homeData->hero->cta : 'Découvrir';

// Extraction de la nouvelle présentation structurée
$presentation = ($homeData && isset($homeData->hero->presentation)) ? $homeData->hero->presentation : null;
$introData    = ($presentation && isset($presentation->intro)) ? $presentation->intro : [];
$langsData    = ($presentation && isset($presentation->languages)) ? $presentation->languages : [];
$outroData    = ($presentation && isset($presentation->outro)) ? $presentation->outro : [];

$skillsTitle = ($homeData && isset($homeData->skillsTitle)) ? $homeData->skillsTitle : 'Compétences';
$skillsList  = ($homeData && isset($homeData->skills)) ? $homeData->skills : [];