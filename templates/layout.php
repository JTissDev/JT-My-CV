<?php

/**
 * Fichier : templates/layout.php
 * Rôle : Structure HTML principale (Gabarit). Assemble toutes les Vues.
 */


// Sécurité d'accès direct
if (!defined('APP_STARTED')) {
    header('HTTP/1.1 403 Forbidden');
    exit('Accès direct interdit.');
}


// Validation de secours pour la variable $lang dans le layout
$htmlLang = (isset($lang) && in_array($lang, ['fr', 'en'], true)) ? $lang : 'fr';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($htmlLang, ENT_QUOTES, 'UTF-8') ?>">
<?php
require_once __DIR__ . '/components/head.php'; ?>

<body>
    <?php
    // 2. Affichage du Header

    require_once __DIR__ . '/components/header.php';?>
    <?= $breadcrumbHtml ?? '' ?>

    <?php
    // 3. Affichage du contenu spécifique de la page (défini dans index.php)
    require_once $viewPath;

    // 4. Affichage du Footer
    require_once __DIR__ . '/components/footer.php';
    ?>

    <!-- Chargement de fichiers JS spécifiques à une page si nécessaire -->
    <?php if (isset($pageJs) && is_array($pageJs)): ?>
        <?php foreach ($pageJs as $jsFile): ?>
            <script src="scripts/<?= htmlspecialchars($jsFile) ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>

</html>