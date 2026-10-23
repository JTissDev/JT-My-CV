<?php

/**
 * Fichier : src/helpers/print-options.php
 * Rôle : Liste blanche des paramètres du module d'impression / export PDF.
 *
 * Utilisé par api/download_pdf.php et print/index.php. Toute valeur absente,
 * non textuelle (ex. ?profil[]=x) ou hors liste retombe sur la valeur par défaut
 * (premier élément de la liste). Ce fichier ne contient que des déclarations :
 * il n'écrit rien dans la sortie.
 */

const PRINT_ALLOWED_PROFILES  = ['informatique', 'commerce', 'industrie'];
const PRINT_ALLOWED_LANGS     = ['fr', 'en'];
const PRINT_ALLOWED_TEMPLATES = ['cv_a4_default'];

/**
 * Valide une valeur reçue contre une liste blanche.
 *
 * @param mixed    $value   Valeur brute reçue (ex. $_GET['profil'])
 * @param string[] $allowed Liste blanche ; le premier élément sert de valeur par défaut
 * @return string La valeur si elle figure dans la liste, sinon la valeur par défaut
 */
function resolvePrintOption(mixed $value, array $allowed): string
{
    return (is_string($value) && in_array($value, $allowed, true)) ? $value : $allowed[0];
}
