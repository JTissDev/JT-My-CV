<?php

/**
 * Main print controller for dynamic A4 CV generation.
 * Handles routing, data enrichment, sorting, and view rendering.
 * 
 * @author    J.Tiss <jtissdev@gmail.com>
 * @since     1.0.0
 * @version   1.4.0
 */
define('PRINT_WANTED', microtime(true));
require_once __DIR__ . '/helpers/debug.php';

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/helpers/registry-helpers.php';
require_once __DIR__ . '/../src/helpers/print-options.php';

// ==========================================
// 1. ANALYSE DE L'URL (Routage)
// ==========================================
// Paramètres validés par liste blanche (src/helpers/print-options.php) : toute valeur
// absente, invalide ou hors liste retombe sur la valeur par défaut (premier élément).
// Lecture via $_GET (et non filter_input) pour que api/download_pdf.php puisse imposer
// ses propres valeurs déjà validées.
$lang = resolvePrintOption($_GET['lang'] ?? null, PRINT_ALLOWED_LANGS);

$profilDefaut = PRINT_ALLOWED_PROFILES[0];
$profilCible  = resolvePrintOption($_GET['profil'] ?? null, PRINT_ALLOWED_PROFILES);

$templateDefaut = PRINT_ALLOWED_TEMPLATES[0];
$templateCible  = resolvePrintOption($_GET['template'] ?? null, PRINT_ALLOWED_TEMPLATES);

// ==========================================
// 2. CHARGEMENT DES DONNÉES (Le Modèle)
// ==========================================
$cheminRegistre = __DIR__ . '/../data/registre.json';
$cheminCvLang   = __DIR__ . "/../data/cv_{$lang}.json";

if (!file_exists($cheminRegistre) || !file_exists($cheminCvLang)) {
    die("Erreur critique : Fichier de données ou de langue introuvable.");
}

if (file_exists($cheminRegistre)) {

    // 1. On charge le fichier brut
    $jsonContent = file_get_contents($cheminRegistre);
    // 2. On le décode en objet stdClass
    $registreData = json_decode($jsonContent);

    // 3. On auto-interpole le registre lui-même en utilisant sa propre clé 'meta'
    if ($registreData && isset($registreData->meta)) {
        $registreData = interpolateRegistry($registreData, $registreData->meta);
    }
} else {
    die("Erreur critique : Fichier de registre introuvable.");
}

if (file_exists($cheminCvLang)) {
    // 1. On charge le fichier de langue brut
    $jsonContent = file_get_contents($cheminCvLang);
    // 2. On le décode en objet stdClass
    $cvLangData = json_decode($jsonContent);

    // 3. On interpole les données de la langue en injectant le registre->meta
    if ($cvLangData && $registreData && isset($registreData->meta)) {
    
        $cvLangData = interpolateRegistry($cvLangData, $registreData->meta);
    }
    
} else {
    die("Erreur critique : Fichier de langue introuvable.");
}


// ==========================================
// 3. LOGIQUE MÉTIER & PRÉPARATION POUR LA VUE
// ==========================================

// --- A. En-tête ---
$headerData = [
    'nom'    => $cvLangData->header->name ?? 'JULIEN TISSIER',
    'profil' => $cvLangData->print->profil_title->$profilCible ?? 'Développeur'
];

// --- B. Contacts ---
$contacts = [];
if (isset($registreData->contacts)) {
    foreach ($registreData->contacts as $key => $data) {
        $label = $cvLangData->contacts->$key->label ?? $key;
        if ($key === 'adress') {
            $value = ($data->street->value ?? '') . ' - ' . ($data->zip->value ?? '') . ' ' . ($data->city->value ?? '');
        } else {
            $value = $data->value ?? '';
        }
        $contacts[] = [
            'key'   => $key,
            'label' => $label,
            'value' => $value
        ];
    }
}

// --- C. Compétences ---
$skillsGrouped = [];
if (isset($registreData->skills)) {
    foreach ($registreData->skills as $key => $comp) {
        $domaines = $comp->domaines ?? [];
        if (in_array($profilCible, $domaines) || in_array('commun', $domaines)) {
            $type = $comp->type ?? 'autres';
            if (!isset($skillsGrouped[$type])) {
                $skillsGrouped[$type] = [
                    'title' => $cvLangData->print->labels->$type ?? ucfirst($type),
                    'items' => []
                ];
            }
            $skillsGrouped[$type]['items'][] = [
                'key'  => $key,
                'name' => $cvLangData->skills->$key->name ?? $key
            ];
        }
    }
}

// --- D. Certifications ---
$certifications = [];
if (isset($registreData->certifications)) {
    foreach ($registreData->certifications as $key => $certif) {
        $domaines = $certif->domaines ?? [];
        if (in_array($profilCible, $domaines) || in_array('commun', $domaines)) {
            $certifications[] = [
                'key'  => $key,
                'name' => $cvLangData->certifications->$key->name ?? $key
            ];
        }
    }
}

// --- E. Langues ---
$languages = [];
if (isset($registreData->languages)) {
    foreach ($registreData->languages as $langKey => $langData) {
        $languages[] = [
            'key'     => $langKey,
            'name'    => $cvLangData->languages->names->$langKey ?? ucfirst($langKey),
            'oral'    => $cvLangData->languages->levels->{$langData->oral_level ?? ''} ?? ($langData->oral_level ?? ''),
            'written' => $cvLangData->languages->levels->{$langData->written_level ?? ''} ?? ($langData->written_level ?? '')
        ];
    }
}

// --- F. Centres d'intérêt ---
$interests = [];
if (isset($registreData->interests)) {
    foreach ($registreData->interests as $interest => $interestData) {
        $interests[] = [
            'key'  => $interest,
            'name' => $cvLangData->interests->$interest->name ?? $interest
        ];
    }
}

// --- G. Parcours (Formations & Expériences) ---
$careerBrut = $registreData->career ?? [];
usort($careerBrut, function ($a, $b) {
    return strcmp($b->dateDebut ?? '', $a->dateDebut ?? '');
});

$career = array_map(function ($item) use ($profilCible, $cvLangData) {
    $idItem     = $item->id ?? '';
    $domaines   = $item->domaines ?? [];
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

// --- G. Libellés généraux pour les sections ---
$labels = $cvLangData->print->labels ?? new stdClass();

// ==========================================
// 4. CHARGEMENT DE LA VUE
// ==========================================
$vueChemin = __DIR__ . "/views/{$templateCible}.php";

if (!file_exists($vueChemin)) {
    $vueChemin = __DIR__ . "/views/{$templateDefaut}.php";
}

require_once $vueChemin;