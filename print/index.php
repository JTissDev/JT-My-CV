<?php
/**
 * Main print controller for dynamic A4 CV generation.
 * Handles routing, data enrichment, sorting, and view rendering.
 * 
 * @author    J.Tiss <jtissdev@gmail.com>
 * @since     1.0.0
 * @version   1.4.0
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
$cvLangData   = json_decode(file_get_contents($cheminCvLang));

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
        $label = $cvLangData->contact->$key->label ?? $key;
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

// --- F. Parcours (Formations & Expériences) ---
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