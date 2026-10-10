<?php
// Vérification de sécurité standard pour s'assurer du passage par le routeur
/* if (!defined('APP_STARTED')) {
    exit('Direct access not permitted');
} */

$currentLang = isset($lang) ? $lang : 'fr';
// Récupération des données spécifiques à la page depuis les traductions globales
$legalsData = (isset($data) && isset($data->pages->legals)) ? $data->pages->legals : null;

// On s'assure qu'un titre par défaut est présent si la clé manque
$pageTitle = $legalsData->title ?? 'Default : Mentions Légales';
$sections = $legalsData->sections ?? [];
