<?php
// Vérification de sécurité standard
/* if (!defined('APP_STARTED')) {
    exit('Direct access not permitted');
} */

$currentlang = isset($lang) ? $lang : 'fr';
// $page contient la clé active passée par le routeur (ex: 'legals', 'cgu-cgv', 'confidentialite')
$currentLegalPage = $page ?? 'legals';

// Récupération des données spécifiques à la page dans le fichier de traduction global
$pageData = isset($data->pages->$currentLegalPage) ? $data->pages->$currentLegalPage : null;

// Variables prêtes pour la vue partagée
$pageTitle = $pageData->title ?? 'mentions légales';
$sections = $pageData->sections ?? []; 