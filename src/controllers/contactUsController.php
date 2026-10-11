<?php
/**
 * Fichier : src/controllers/contactUsController.php
 * Rôle : Contrôleur pour la page "Contactez-moi". Gère l'affichage du formulaire de contact et le traitement des soumissions.
 */

$contactUsData = (isset($data) && isset($data->pages->contactUs)) ? $data->pages->contactUs : null;
$contactUsregistre = (isset($registre) && isset($registre->contactUs)) ? $registre->contactUs : null;

$selectOptions = [];
foreach ($contactUsregistre->select as $key => $value) {
    
    $selectOptions[$key] = [
        'key'    => $key,
        'object' => $contactUsregistre->select->$key->object,
        'label'  => $contactUsData->fields->object->options->$key ?? $key
    ];
    
}