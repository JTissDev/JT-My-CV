<?php
/**
 * Fichier : index.php
 * Rôle : Routeur principal (Front Controller).
 * Il intercepte la requête, charge les données et assemble la page.
 */
define('APP_STARTED', microtime(true));
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ .'/src/helpers/registry-helpers.php';

// ==========================================
// 1. ANALYSE DE L'URL (Routage)
// ==========================================
$lang = isset($_GET['lang']) && $_GET['lang'] === 'en' ? 'en' : 'fr';
$page = isset($_GET['page']) ? $_GET['page'] : 'home';


// ==========================================
// 2. CHARGEMENT DES DONNÉES (Le Modèle)
// ==========================================
$registreFile = __DIR__ . "/data/registre.json";
$registre = null;

if (file_exists($registreFile)) {
    // 1. On charge le fichier brut
    $jsonContent = file_get_contents($registreFile);
    // 2. On le décode en objet stdClass
    $registre = json_decode($jsonContent);
    
    // 3. On auto-interpole le registre lui-même en utilisant sa propre clé 'meta'
    if ($registre && isset($registre->meta)) {
        $registre = interpolateRegistry($registre, $registre->meta);
    }
} else {
    die("Erreur critique : Fichier de registre introuvable.");
}

$dataFile = __DIR__ . "/data/cv_{$lang}.json";
$data = null;

if (file_exists($dataFile)) {
    // 1. On charge le fichier de langue brut
    $jsonContent = file_get_contents($dataFile);
    // 2. On le décode en objet stdClass
    $data = json_decode($jsonContent);
    
    // 3. On interpole les données de la langue en injectant le registre->meta
    if ($data && $registre && isset($registre->meta)) {
        $data = interpolateRegistry($data, $registre->meta);
    }
} else {
    die("Erreur critique : Fichier de langue introuvable.");
}



// ==========================================
// 3. VALIDATION ET PRÉPARATION (Logique globale)
// ==========================================
// Définition des pages légales partageant la même structure DRY
$legalPages = ['legals', 'cgu-cgv', 'confidentialite'];

if (in_array($page, $legalPages)) {
    
    // Contrôleur unique et vue partagée pour toutes les pages légales
    $pageControllerPath = __DIR__ . "/src/controllers/legalsController.php";
    $viewPath           = __DIR__ . "/templates/pages/legals.php";
} else {
    // Comportement standard pour le reste du site
    $pageControllerPath = __DIR__ . "/src/controllers/{$page}Controller.php";
    $viewPath           = __DIR__ . "/templates/pages/{$page}.php";
}

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
    'home'           => ['pages/home.css'],
    'creations'      => ['components/gallery.css'],
    'career'         => ['pages/career.css'],
    "print"          => ['pages/print.css'],
    '404'            => ['pages/error404.css'],
    'legals'         => ['pages/legals.css'],
    'cgu-cgv'        => ['pages/legals.css'],
    'confidentialite'=> ['pages/legals.css']
];
$pageCss = isset($pageCssMap[$page]) ? $pageCssMap[$page] : [];

// Définition des JS spécifiques par page
$pageJsMap = [
    'print' => ['print.js'] // On charge print.js uniquement sur la page ?page=print
];
$pageJs = isset($pageJsMap[$page]) ? $pageJsMap[$page] : [];



if (file_exists($pageControllerPath)) {
    require_once $pageControllerPath;
}


// ==========================================
// 4. PRÉPARATION DES COMPOSANTS ET AFFICHAGE
// ==========================================

// 4.1 On charge d'abord toute la logique des composants
require_once __DIR__ . '/src/helpers/header-data.php';
require_once __DIR__ . '/src/helpers/footer-data.php';

// 4.2 On délègue l'affichage HTML au gabarit principal
require_once __DIR__ . '/templates/layout.php';