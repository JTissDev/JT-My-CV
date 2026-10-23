<?php
/**
 * Fichier : src/controllers/homeController.php
 * Rôle : Contrôleur de la page d'accueil. 
 * Prépare et instancie les composants objets (SitePresComponent et HeroPresComponent).
 */

if (!defined('APP_STARTED')) {
    die('Accès direct interdit');
}


// 1. Inclusion des classes de composants si elles ne sont pas chargées par l'autoloader
require_once OBJECTCOMPDIR . '/HeroPresComponent.php';
require_once OBJECTCOMPDIR . '/SitePresComponent.php';

// 2. Récupération sécurisée des données de la page d'accueil depuis $data (langue active)
$homeData = isset($data->pages->home) ? $data->pages->home : null;

$pagedata= new stdClass();
$pagedata->title=$data->header->name?? 'def : Julien Tissier';
$pagedata->subtitle=$data->header->title?? 'Développeur Web';

// 3. Instanciation du composant de présentation du site (SitePresComponent)
$sitePresRegistre = new stdClass();
$sitePresRegistre->routes = new stdClass();
$sitePresRegistre->routes->visiteurs = 'creations';
$sitePresRegistre->routes->recruteurs = 'career';
$sitePresRegistre->currentpage='home';

$sitePresData     = isset($homeData->hero->Intro) ? $homeData->hero->Intro : new stdClass();

$sitePresComponent = new SitePresComponent($sitePresRegistre, $sitePresData, $lang);

// 4. Instanciation du composant de présentation personnelle (HeroPresComponent)
$heroPresRegistre = isset($registre->home) ? $registre->home : new stdClass();
$heroPresData     = isset($homeData->hero->presentation) ? $homeData->hero->presentation : new stdClass();

$heroPresComponent = new HeroPresComponent($heroPresRegistre, $heroPresData, $lang);

// 5. Variables complémentaires éventuelles pour la vue (ex: salutations, titre global)
$pageTitle = $homeData->title ?? 'Accueil';

$careerUrl = getCleanUrl('career', $lang);
$careerLabel = ($lang === 'en') ? 'see my career' : 'voir mon parcours';
$careerButton = new LinkButton($careerLabel, $careerUrl, 'home-nav-button');

$creationUrl = getCleanUrl('creations', $lang);
$creationLabel = ($lang === 'en') ? 'see my creations' : 'voir mon portefolio';
$creationButton = new LinkButton($creationLabel, $creationUrl, 'home-nav-button');

