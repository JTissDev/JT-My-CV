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

$currentLang = isset($lang) ? $lang : 'fr';

?>


<head>
    <!-- 1 Encodage et configuration -->
    <!-- Encodage des caractères pour les accents -->
    <meta charset="UTF-8">
    <base href="<?= htmlspecialchars($baseHref ?? '/', ENT_QUOTES, 'UTF-8') ?>">
    <!-- Configuration du responsive pour mobiles et tablettes -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Titre dynamique de l'onglet -->
    <title><?= htmlspecialchars($meta->pageTitle ?? 'Julien Tissier - Développeur Web') ?></title>

    <!-- 3 SEO, Meta-Description & Reseaux Sociaux-->
    <!-- Description dynamique pour les moteurs de recherche (SEO) -->
    <meta name="description" content="<?= htmlspecialchars($meta->pageDescription) ?? '' ?>">
    <meta property="og:title" content="<?= htmlspecialchars($meta->pageTitle) ?? '' ?>">
    <meta property="og:description" content="<?= htmlspecialchars($meta->pageSocialDesc) ?? '' ?>">
    <!-- <meta property="og:image" content="https://exemple.com"> -->
    <link rel="canonical" href="<?= htmlspecialchars($meta->url) ?? 'https://www.jtissier-developpeur-informatique.fr/' ?>">

    <!-- 4. Performance & Connexions à des domaines tiers (DNS / Polices) -->
    <!-- <link rel="preconnect" href="https://googleapis.com"> -->
    <!-- <link rel="preconnect" href="https://gstatic.com" crossorigin> -->


    <!-- 5. Stylesheets (Fichiers CSS) -->
    <!-- Feuille de style principale commune à TOUTES les pages -->
    <link rel="stylesheet" href="styles/main.css">

    <!-- Chargement de fichiers CSS spécifiques à une page si nécessaire -->
    <?php if (isset($pageCss) && is_array($pageCss)): ?>
        <?php foreach ($pageCss as $cssFile): ?>
            <link rel="stylesheet" href="styles/<?= htmlspecialchars($cssFile) ?>">
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- 6. Icons (Favicons de l'application) -->
    <!-- Favicon (icône de l'onglet) -->
    <link rel="icon" type="image/svg+xml" href="assets/images/jt-icon.svg">

    <!-- 7. Scripts JavaScript critiques ou asynchrones -->

    <!-- 8. Injection BreadCrumb -->
     <?= $breadcrumbSchema ?? '' ?>
</head