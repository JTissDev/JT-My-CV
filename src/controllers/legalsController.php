<?php
// Vérification de sécurité standard pour s'assurer du passage par le routeur
/* if (!defined('APP_STARTED')) {
    exit('Direct access not permitted');
} */

// Récupération des données spécifiques à la page depuis les traductions globales
$legalsData = $translations['legals'] ?? [];

// On s'assure qu'un titre par défaut est présent si la clé manque
$pageTitle = $legalsData['title'] ?? 'Mentions Légales';
$sections = $legalsData['sections'] ?? [];