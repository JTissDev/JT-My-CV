<?php

if (!defined('APP_STARTED')) {
    exit('Direct access not permitted');
}

/**
 * Fonction publique principale : 
 * Point d'entrée qui accepte et retourne la structure initiale (stdClass, array ou string).
 */
function interpolateRegistry(mixed $data, array|stdClass $registry): mixed {
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
function interpolateObject(stdClass $obj, array $registry): stdClass {
    foreach ($obj as $key => $value) {
        $obj->$key = interpolateRegistry($value, $registry);
    }
    return $obj;
}

/**
 * Traite spécifiquement les tableaux et retourne un tableau
 */
function interpolateArray(array $array, array $registry): array {
    foreach ($array as $key => $value) {
        $array[$key] = interpolateRegistry($value, $registry);
    }
    return $array;
}

/**
 * Effectue le remplacement des balises {cle} dans une chaîne de caractères
 */
function interpolateString(string $text, array $registry): string {
    foreach ($registry as $key => $value) {
        if (is_string($value)) {
            $text = str_replace('{' . $key . '}', $value, $text);
        }
    }
    return $text;
}