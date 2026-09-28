<?php
/**
 * Fichier : includes/head.php
 * Rôle : Génère la balise <head> HTML commune à toutes les pages de façon dynamique.
 * 
 * Variables attendues en provenance de index.php :
 * - $pageTitle (string) : Le titre de la page affiché dans l'onglet du navigateur
 * - $pageDescription (string) : La description meta pour le référencement (SEO)
 * - $pageCss (array) : Tableau d'éventuels fichiers CSS spécifiques à la page
 * - $lang (string) : La langue actuelle de la page (ex: 'fr', 'en')
 */

// Valeurs par défaut au cas où la page n'en définit pas
$title = isset($pageTitle) ? $pageTitle . ' | Julien Tissier' : 'Julien Tissier - Développeur Web';
$description = isset($pageDescription) ? $pageDescription : 'Portfolio et CV de Julien Tissier, Développeur Web Full Stack.';
$currentLang = isset($lang) ? $lang : 'fr';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($currentLang) ?>">
<head>
    <!-- Encodage des caractères pour les accents -->
    <meta charset="UTF-8">
    
    <!-- Configuration du responsive pour mobiles et tablettes -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Titre dynamique de l'onglet -->
    <title><?= htmlspecialchars($title) ?></title>
    
    <!-- Description dynamique pour les moteurs de recherche (SEO) -->
    <meta name="description" content="<?= htmlspecialchars($description) ?>">
    
    <!-- Favicon (icône de l'onglet) -->
    <link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">
    
    <!-- Feuille de style principale commune à TOUTES les pages -->
    <link rel="stylesheet" href="styles/main.css">
    
    <!-- Chargement de fichiers CSS spécifiques à une page si nécessaire -->
    <?php if (isset($pageCss) && is_array($pageCss)): ?>
        <?php foreach ($pageCss as $cssFile): ?>
            <link rel="stylesheet" href="styles/<?= htmlspecialchars($cssFile) ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>