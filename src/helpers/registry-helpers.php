<?php

if (!defined('APP_STARTED') && !defined('PRINT_WANTED')) {
    exit('Direct access not permitted');
}

/**
 * Fonction publique principale : 
 * Point d'entrée qui accepte et retourne la structure initiale (stdClass, array ou string).
 */
function interpolateRegistry(mixed $data, array|stdClass $registry): mixed
{
    // Normalisation du registre en tableau pour simplifier les remplacements par clé
    $registryArray = is_object($registry) ? (array) $registry : $registry;

    if (is_object($data)) {
        return interpolateObject($data, $registryArray);
    }

    if (is_array($data)) {
        return interpolateArray($data, $registryArray);
    }

    if (is_string($data)) {
        return interpolateString($data, $registryArray);
    }

    return $data;
}

/**
 * Traite spécifiquement les objets (stdClass) et retourne un stdClass
 */
function interpolateObject(stdClass $obj, array $registry): stdClass
{
    foreach ($obj as $key => $value) {
        $obj->$key = interpolateRegistry($value, $registry);
    }
    return $obj;
}

/**
 * Traite spécifiquement les tableaux et retourne un tableau
 */
function interpolateArray(array $array, array $registry): array
{
    foreach ($array as $key => $value) {
        $array[$key] = interpolateRegistry($value, $registry);
    }
    return $array;
}

/**
 * Effectue le remplacement des balises {cle} dans une chaîne de caractères
 */
function interpolateString(string $text, array $registry): string
{
    foreach ($registry as $key => $value) {
        if (is_string($value)) {
            $text = str_replace('{' . $key . '}', $value, $text);
        }
    }
    return $text;
}

/**
 * Génère une URL propre en récupérant dynamiquement le slug 'name' 
 * depuis les fichiers JSON de langue (cv_fr.json / cv_en.json)
 * 
 * @param string|null $internalPage La clé technique interne (ex: 'contactUs', 'career', 'home')
 * @param string $lang La langue active ('fr' ou 'en')
 * @return string L'URL propre
 */
function getCleanUrl(?string $internalPage, string $lang = 'fr'): string {
    if (empty($internalPage)) {
        $internalPage = 'home';
    }

    $lang = in_array($lang, ['fr', 'en'], true) ? $lang : 'fr';
    $slug = $internalPage; // Fallback par défaut

    // 1. Charger dynamiquement le fichier JSON de la langue cible (cv_fr.json ou cv_en.json)
    $dataFile = dirname(__DIR__, 2) . "/data/cv_{$lang}.json";
    if (file_exists($dataFile)) {
        $langJson = json_decode(file_get_contents($dataFile));
        // Si la page existe dans ce fichier de langue, on récupère son "name" spécifique à cette langue
        if (isset($langJson->meta->pages->$internalPage->name)) {
            $slug = $langJson->meta->pages->$internalPage->name;
        }
    }

    // 2. Détection automatique du sous-dossier du projet (ex: /JT-Developpeur)
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $baseDir = rtrim(dirname($scriptName), '/\\');

    return $baseDir . '/' . $slug . '/' . $lang;
}