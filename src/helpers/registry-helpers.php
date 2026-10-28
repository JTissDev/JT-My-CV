<?php

if (!defined('APP_STARTED') && !defined('PRINT_WANTED')) {
    exit('Direct access not permitted');
}

require_once __DIR__ . '/request-helpers.php';

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
 * Slug d'URL d'une page dans une langue, lu dans meta.pages.<page>.name du fichier de langue
 * (cv_fr.json / cv_en.json). Si la page n'y figure pas, la clé interne sert de slug.
 *
 * Le fichier de langue n'est lu qu'une fois par langue et par requête : le menu et le pied de page
 * appellent getCleanUrl() une dizaine de fois.
 *
 * @param string $internalPage La clé technique interne (ex: 'contactUs', 'career', 'home')
 * @param string $lang La langue ('fr' ou 'en'; toute autre valeur donne 'fr')
 * @return string Le slug, ex: 'me-contacter'
 */
function pageSlug(string $internalPage, string $lang = 'fr'): string
{
    static $slugsByLang = [];

    $lang = in_array($lang, ['fr', 'en'], true) ? $lang : 'fr';

    if (!isset($slugsByLang[$lang])) {
        $slugsByLang[$lang] = [];
        $dataFile = dirname(__DIR__, 2) . "/data/cv_{$lang}.json";

        if (is_file($dataFile)) {
            $langJson = json_decode((string) file_get_contents($dataFile), true);
            $pages = is_array($langJson) ? ($langJson['meta']['pages'] ?? null) : null;

            if (is_array($pages)) {
                foreach ($pages as $pageKey => $pageConf) {
                    if (is_array($pageConf) && isset($pageConf['name']) && is_string($pageConf['name']) && $pageConf['name'] !== '') {
                        $slugsByLang[$lang][(string) $pageKey] = $pageConf['name'];
                    }
                }
            }
        }
    }

    return $slugsByLang[$lang][$internalPage] ?? $internalPage;
}

/**
 * Génère une URL propre en récupérant dynamiquement le slug 'name'
 * depuis les fichiers JSON de langue (cv_fr.json / cv_en.json)
 *
 * Le dossier du site (ex: /JT-Developpeur sous Laragon, rien en production) vient de
 * appBaseFolder() (request-helpers.php), comme la balise <base href> : une seule source.
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

    return appBaseFolder() . '/' . pageSlug($internalPage, $lang) . '/' . $lang;
}
