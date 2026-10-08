<?php
/**
 * Fichier : index.php
 * Rôle : Routeur principal (Front Controller).
 * Il intercepte la requête, charge les données et assemble la page.
 */

// ==========================================
// 1. ANALYSE DE L'URL (Routage)
// ==========================================
$lang = isset($_GET['lang']) && $_GET['lang'] === 'en' ? 'en' : 'fr';
$page = isset($_GET['page']) ? $_GET['page'] : 'home';


// ==========================================
// 2. CHARGEMENT DES DONNÉES (Le Modèle)
// ==========================================
$dataFile = __DIR__ . "/data/cv_{$lang}.json";
$data = null;

if (file_exists($dataFile)) {
    $jsonContent = file_get_contents($dataFile);
    $data = json_decode($jsonContent);
} else {
    die("Erreur critique : Fichier de langue introuvable.");
}


// ==========================================
// 3. VALIDATION ET PRÉPARATION (Logique globale)
// ==========================================
$viewPath = __DIR__ . "/templates/pages/{$page}.php";

// Validation complète de la page (JSON + Fichier physique)
if ($data && isset($data->meta->pages->$page) && file_exists($viewPath)) {
    $pageTitle       = $data->meta->pages->$page->title;
    $pageDescription = $data->meta->pages->$page->description;
} else {
    // Gestion de l'erreur 404
    $pageTitle       = ($lang === 'en') ? 'Page Not Found' : 'Page non trouvée';
    $pageDescription = ($lang === 'en') ? 'The requested page does not exist.' : 'La page demandée n\'existe pas.';
    $page            = '404'; 
    $viewPath        = __DIR__ . "/templates/pages/404.php"; 
}

// Définition des CSS spécifiques par page
$pageCssMap = [
    'home'      => ['pages/home.css'],
    'creations' => ['components/gallery.css'],
    'parcours'  => ['pages/parcours.css'],
    "print"     => ['pages/print.css'],
    '404'       => ['pages/error404.css']
];
$pageCss = isset($pageCssMap[$page]) ? $pageCssMap[$page] : [];

// Définition des JS spécifiques par page
$pageJsMap = [
    'print' => ['print.js'] // On charge print.js uniquement sur la page ?page=print
];
$pageJs = isset($pageJsMap[$page]) ? $pageJsMap[$page] : [];

// Chargement automatique du contrôleur de la page (s'il en a un, comme 404Controller.php)
$pageControllerPath = __DIR__ . "/src/controllers/{$page}Controller.php";


if (file_exists($pageControllerPath)) {
    require_once $pageControllerPath;
}


// ==========================================
// 4. PRÉPARATION DES COMPOSANTS ET AFFICHAGE
// ==========================================

// 4.1 On charge d'abord toute la logique des composants
require_once __DIR__ . '/src/Helpers/header-data.php';
require_once __DIR__ . '/src/Helpers/footer-data.php';

// 4.2 On délègue l'affichage HTML au gabarit principal
require_once __DIR__ . '/templates/layout.php';