<?php
/**
 * Fichier : index.php
 * Rôle : Routeur principal (Front Controller) avec URLs dynamiques et propres (/pagename/lang)
 */

if (!defined('APP_STARTED')) {
    define('APP_STARTED', true);
}

// Inclusions de base
// (pas de session ici : seule la page de contact en ouvre une, voir startAppSession())
define('OBJECTCOMPDIR', __DIR__ . '/classes/components');
require_once OBJECTCOMPDIR . '/Button.php';

// ==========================================
// INCLUSIONS DE BASE
// ==========================================
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/helpers/registry-helpers.php';
require_once __DIR__ . '/src/helpers/request-helpers.php';
require_once __DIR__ . '/src/helpers/router-helpers.php';

// ==========================================
// BALISE <base href> (utilisée par templates/components/head.php)
// ==========================================
// Chemin du site seul ('/' en production, '/JT-Developpeur/' sous Laragon), sans nom d'hôte :
// l'en-tête Host envoyé par le visiteur n'est jamais recopié dans la page.
$baseHref = appBaseHref();

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
    http_response_code(500);
    die("Erreur critique : Fichier de registre introuvable.");
}

if (!($registre instanceof stdClass) || !isset($registre->meta->site_url)) {
    http_response_code(500);
    die("Erreur critique : Fichier de registre invalide.");
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


// ==========================================
// 2. RÉSOLUTION DE LA ROUTE
// ==========================================
// Les paramètres sont lus sans jamais planter (?page[]=x, ?lang[]=en...) :
// $page vaut null quand l'adresse ne correspond à aucune page, c'est alors une vraie 404 (voir plus bas).
$aliases = routerBuildAliases($slugMap);
$route   = routerResolve($_GET, $aliases);
$page    = $route['page'];
$lang    = $route['lang'];

// Une page n'a qu'une seule adresse : /slug-de-la-langue/langue. Toute autre forme (slug de l'autre
// langue, casse, langue absente ou inconnue, slash final, ancien index.php?page=...) est redirigée en 301.
if ($page !== null) {
    $redirectTo = routerCanonicalRedirect(
        $route,
        pageSlug($page, $lang),
        (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
        (string) ($_SERVER['REQUEST_URI'] ?? '/'),
        appBaseFolder(),
        $_GET,
        $aliases
    );

    if ($redirectTo !== null) {
        header('Location: ' . $redirectTo, true, 301);
        exit;
    }
}


// ==========================================
// 3. CHARGEMENT DES DONNÉES DE LANGUE
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
    http_response_code(500);
    die("Erreur critique : Fichier de langue introuvable.");
}


// ==========================================
// 4. VALIDATION ET ROUTAGE DES VUES
// ==========================================
$legalPages = ['legals', 'cgu-cgv', 'confidentialite'];

$notFound           = ($page === null);
$pageControllerPath = '';
$viewPath           = '';

if (!$notFound) {
    if (in_array($page, $legalPages, true)) {
        $pageControllerPath = __DIR__ . "/src/controllers/legalsController.php";
        $viewPath           = __DIR__ . "/templates/pages/legals.php";
    } else {
        $pageControllerPath = __DIR__ . "/src/controllers/{$page}Controller.php";
        $viewPath           = __DIR__ . "/templates/pages/{$page}.php";
    }

    $notFound = !($data && isset($data->meta->pages->$page) && file_exists($viewPath));
}

$meta = new stdClass();

// Un contrôleur positive $routeNotFound quand l'adresse est bien celle d'une page mais que son
// contenu n'existe pas (ex. ?creation=inconnu) : même 404 que pour une page inconnue.
$routeNotFound = false;

if (!$notFound) {
    $meta->pageTitle       = $data->meta->pages->$page->title;
    $meta->pageDescription = $data->meta->pages->$page->description;
    $meta->url             = $registre->meta->site_url . '/' . pageSlug($page, $lang) . '/' . $lang;
    $meta->pageSocialDesc  = $data->meta->pages->$page->social_description ?? $meta->pageDescription;

    if (file_exists($pageControllerPath)) {
        require_once $pageControllerPath;
    }

    $notFound = ($routeNotFound === true);
}

if ($notFound) {
    $page               = '404';
    $pageControllerPath = __DIR__ . "/src/controllers/404Controller.php";
    $viewPath           = __DIR__ . "/templates/pages/404.php";

    $meta = new stdClass();
    $meta->pageTitle       = ($lang === 'en') ? 'Page Not Found' : 'Page non trouvée';
    $meta->pageDescription = ($lang === 'en') ? 'The requested page does not exist.' : 'La page demandée n\'existe pas.';
    $meta->url             = null; // une page d'erreur n'a pas d'adresse canonique (voir head.php)
    $meta->pageSocialDesc  = $meta->pageDescription;

    // Vrai code HTTP 404 : les moteurs de recherche n'indexent pas la page
    http_response_code(404);
    header('X-Robots-Tag: noindex, nofollow');

    require_once $pageControllerPath;
}

// Maps CSS et JS
$pageCssMap = [
    'home'            => ['pages/home.css'],
    'creations'       => ['pages/creations.css'],
    'career'          => ['pages/career.css'],
    'print'           => ['pages/print.css'],
    '404'             => ['pages/error404.css'],
    'legals'          => ['pages/legals.css'],
    'cgu-cgv'         => ['pages/legals.css'],
    'confidentialite' => ['pages/legals.css'],
    'contactUs'       => ['pages/contactUs.css'],

];
$pageCss = $pageCssMap[$page] ?? [];

$pageJsMap = [
    'print' => ['print.js']
];
$pageJs = $pageJsMap[$page] ?? [];


// ==========================================
// 5. AFFICHAGE
// ==========================================
require_once __DIR__ . '/src/helpers/header-data.php';
require_once __DIR__ . '/src/helpers/footer-data.php';
require_once __DIR__ . '/templates/layout.php';
