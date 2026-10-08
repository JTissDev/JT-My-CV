<?php
/**
 * Main print controller for dynamic A4 CV generation.
 * Handles routing, data enrichment, sorting, and view rendering.
 * 
 * @author    J.Tiss <jtissdev@gmail.com>
 * @since     1.0.0
 * @version   1.3.0
 */
require_once __DIR__ . '/helpers/debug.php';

// ==========================================
// 1. ANALYSE DE L'URL (Routage)
// ==========================================
$lang = isset($_GET['lang']) && $_GET['lang'] === 'en' ? 'en' : 'fr';

$profilDefaut = 'informatique';
$profilCible  = filter_input(INPUT_GET, 'profil', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? $profilDefaut;

$templateDefaut = 'cv_a4_default';
$templateCible  = filter_input(INPUT_GET, 'template', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? $templateDefaut;

// ==========================================
// 2. CHARGEMENT DES DONNÉES (Le Modèle)
// ==========================================
$cheminRegistre = __DIR__ . '/../data/registre.json';
$cheminCvLang   = __DIR__ . "/../data/cv_{$lang}.json";

if (!file_exists($cheminRegistre) || !file_exists($cheminCvLang)) {
    die("Erreur critique : Fichier de données ou de langue introuvable.");
}

$registreData = json_decode(file_get_contents($cheminRegistre));
$cvLangData   = json_decode(file_get_contents($cvLangData ?? $cheminCvLang) ? file_get_contents($cheminCvLang) : null);

// ==========================================
// 3. LOGIQUE MÉTIER & ENRICHISSEMENT
// ==========================================

function filtrerEtTrierParDomaine(array $elements, string $profilRecherche): array {
    $filtre = array_filter($elements, function ($item) use ($profilRecherche) {
        return isset($item->domaines) && is_array($item->domaines) && (in_array($profilRecherche, $item->domaines) || in_array('commun', $item->domaines));
    });

    usort($filtre, function ($a, $b) {
        $prioA = $a->print_priority ?? 99;
        $prioB = $b->print_priority ?? 99;
        return $prioA <=> $prioB;
    });

    return $filtre;
}

// Récupération et tri chronologique décroissant de tout le parcours
$careerBrut = $registreData->career ?? [];
usort($careerBrut, function ($a, $b) {
    return strcmp($b->dateDebut ?? '', $a->dateDebut ?? '');
});

// Enrichissement des données du parcours pour la vue (Séparation Logique / Vue)
$career = array_map(function ($item) use ($profilCible, $cvLangData) {
    $idItem = $item->id ?? '';
    $domaines = $item->domaines ?? [];
    $isRelevant = in_array($profilCible, $domaines) || in_array('commun', $domaines);
    
    $textesItem = $cvLangData->career_texts->$idItem ?? null;
    
    return [
        'id'          => $idItem,
        'category'    => $item->category ?? 'experience',
        'dateDebut'   => $item->dateDebut ?? '',
        'dateFin'     => $item->dateFin ?? 'Présent',
        'titre'       => $textesItem->name ?? $idItem,
        'entreprise'  => $item->entreprise ?? $item->etablissement ?? '',
        'ville'       => $item->ville ?? '',
        'dept'        => $item->dept ?? null,
        'isRelevant'  => $isRelevant,
        'description' => $isRelevant ? ($textesItem->description ?? []) : []
    ];
}, $careerBrut);

$competences = filtrerEtTrierParDomaine($registreData->competences ?? [], $profilCible);

// ==========================================
// 4. CHARGEMENT DE LA VUE
// ==========================================
$vueChemin = __DIR__ . "/views/{$templateCible}.php";

if (!file_exists($vueChemin)) {
    $vueChemin = __DIR__ . "/views/{$templateDefaut}.php";
}

require_once $vueChemin;