<?php
/**
 * Fichier : index.php
 * Rôle : Routeur principal (Front Controller) avec URLs dynamiques et propres (/pagename/lang)
 */

if (!defined('APP_STARTED')) {
    define('APP_STARTED', true);
}

// Gestion propre des sessions et des inclusions de base
session_start();

// ==========================================
// DÉTECTION AUTOMATIQUE DE L'URL DE BASE
// ==========================================
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$scriptDir = dirname($_SERVER['SCRIPT_NAME']);
$baseFolder = ($scriptDir === '/' || $scriptDir === '\\') ? '' : $scriptDir;

// Variable globale ou injectée dans ton registre
$dynamicSiteUrl = $protocol . $host . $baseFolder;

// ==========================================
// INCLUSIONS DE BASE
// ==========================================
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/helpers/registry-helpers.php';

// ==========================================
// 1. CHARGEMENT DU REGISTRE ET MAPPING DYNAMIQUE
// ==========================================
$registreFile = __DIR__ . "/data/registre.json";
$registre = null;
$slugMap = [];

if (file_exists($registreFile)) {
    $jsonContent = file_get_contents($registreFile);
    $registre = json_decode($jsonContent);
    
    // Interpolation du registre
    if ($registre && isset($registre->meta)) {
        $registre = interpolateRegistry($registre, $registre->meta);
        
        // Récupération dynamique des slugs depuis le registre si définis, sinon fallback propre
        if (isset($registre->meta->slugs)) {
            $slugMap = (array)$registre->meta->slugs;
        }
    }
} else {
    die("Erreur critique : Fichier de registre introuvable.");
}

// Fallback de sécurité si les slugs ne sont pas encore dans le JSON
if (empty($slugMap)) {
    $slugMap = [
        'accueil'          => 'home',
        'home'             => 'home',
        'parcours'         => 'career',
        'cv'               => 'career',
        'contact'          => 'contactUs',
        'contactus'        => 'contactUs',
        'mentions-legales' => 'legals',
        'legals'           => 'legals',
        'cgu-cgv'          => 'cgu-cgv',
        'confidentialite'  => 'confidentialite',
        'impression'       => 'print',
        'print'            => 'print',
    ];
}

// Récupération et nettoyage des paramètres URL
$rawPage = isset($_GET['page']) ? strtolower(trim($_GET['page'])) : 'accueil';
$lang    = isset($_GET['lang']) ? strtolower(trim($_GET['lang'])) : 'fr';

// Résolution de la page interne via le mapping dynamique
$page = isset($slugMap[$rawPage]) ? $slugMap[$rawPage] : 'home';

// Sécurisation de la langue
$allowedLangs = ['fr', 'en'];
if (!in_array($lang, $allowedLangs, true)) {
    $lang = isset($_SESSION['lang']) && in_array($_SESSION['lang'], $allowedLangs, true) 
            ? $_SESSION['lang'] 
            : 'fr';
}
$_SESSION['lang'] = $lang;


// ==========================================
// 2. CHARGEMENT DES DONNÉES DE LANGUE
// ==========================================
$dataFile = __DIR__ . "/data/cv_{$lang}.json";
$data = null;

if (file_exists($dataFile)) {
    $jsonContent = file_get_contents($dataFile);
    $data = json_decode($jsonContent);
    
    if ($data && $registre && isset($registre->meta)) {
        $data = interpolateRegistry($data, $registre->meta);
    }
} else {
    die("Erreur critique : Fichier de langue introuvable.");
}


// ==========================================
// 3. VALIDATION ET ROUTAGE DES VUES
// ==========================================
$legalPages = ['legals', 'cgu-cgv', 'confidentialite'];

if (in_array($page, $legalPages)) {
    $pageControllerPath = __DIR__ . "/src/controllers/legalsController.php";
    $viewPath           = __DIR__ . "/templates/pages/legals.php";
} else {
    $pageControllerPath = __DIR__ . "/src/controllers/{$page}Controller.php";
    $viewPath           = __DIR__ . "/templates/pages/{$page}.php";
}

$meta = new stdClass();

if ($data && isset($data->meta->pages->$page) && file_exists($viewPath)) {
    $meta->pageTitle       = $data->meta->pages->$page->title;
    $meta->pageDescription = $data->meta->pages->$page->description;
    $meta->url             = $registre->meta->site_url . '/' . $rawPage . '/' . $lang;
    $meta->pageSocialDesc  = $data->meta->pages->$page->social_description ?? $meta->pageDescription;
} else {
    $meta->pageTitle       = ($lang === 'en') ? 'Page Not Found' : 'Page non trouvée';
    $meta->pageDescription = ($lang === 'en') ? 'The requested page does not exist.' : 'La page demandée n\'existe pas.';
    $page                  = '404'; 
    $viewPath              = __DIR__ . "/templates/pages/404.php"; 
    $meta->url             = $registre->meta->site_url . '/404/' . $lang;
    $meta->pageSocialDesc  = $meta->pageDescription;
}

// Maps CSS et JS
$pageCssMap = [
    'home'            => ['pages/home.css'],
    'creations'       => ['components/gallery.css'],
    'career'          => ['pages/career.css'],
    'print'           => ['pages/print.css'],
    '404'             => ['pages/error404.css'],
    'legals'          => ['pages/legals.css'],
    'cgu-cgv'         => ['pages/legals.css'],
    'confidentialite' => ['pages/legals.css'],
    'contactUs'       => ['pages/contactUs.css']
];
$pageCss = $pageCssMap[$page] ?? [];

$pageJsMap = [
    'print' => ['print.js']
];
$pageJs = $pageJsMap[$page] ?? [];

if (file_exists($pageControllerPath)) {
    require_once $pageControllerPath;
}


// ==========================================
// 4. AFFICHAGE
// ==========================================
require_once __DIR__ . '/src/helpers/header-data.php';
require_once __DIR__ . '/src/helpers/footer-data.php';
require_once __DIR__ . '/templates/layout.php';