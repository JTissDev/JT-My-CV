<?php
/**
 * Fichier : templates/layout.php
 * Rôle : Structure HTML principale (Gabarit). Assemble toutes les Vues.
 */

// 1. Inclusion des balises <head>
require_once __DIR__ . '/components/head.php';

// 2. Affichage du Header
require_once __DIR__ . '/components/header.php';

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