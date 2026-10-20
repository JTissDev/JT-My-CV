<?php
/**
 * Fichier : templates/pages/creation-detail.php
 * Rôle : Vue détaillée d'une création (100% muette, affichage direct des variables préparées).
 */

if (!defined('APP_STARTED')) {
    die('Accès direct interdit');
}
?>

<div class="creations-page">
    <div class="creation-detail">
        <!-- Utilisation stricte du helper global getCleanUrl pour l'URL propre de retour -->
        <a href="<?= htmlspecialchars($projectBackUrl) ?>" class="btn-back">← Retour aux créations</a>

        <header class="detail-header">
            <span class="badge-category"><?= htmlspecialchars($projectCategory) ?></span>
            <h1><?= htmlspecialchars($projectTitle) ?></h1>
            <span class="project-year"><?= htmlspecialchars($projectYear) ?></span>
        </header>

        <?php if (!empty($projectImage)): ?>
            <div class="detail-media">
                <img src="<?= htmlspecialchars($projectImage) ?>" alt="<?= htmlspecialchars($projectTitle) ?>">
            </div>
        <?php endif; ?>

        <div class="detail-content">
            <h2>Description du projet</h2>
            <div class="description-paragraphs">
                <?php foreach ($projectParagraphs as $paragraph): ?>
                    <p><?= htmlspecialchars($paragraph) ?></p>
                <?php endforeach; ?>
            </div>

            <?php if (!empty($projectTags)): ?>
                <div class="tags-section">
                    <h3>Technologies & Outils</h3>
                    <div class="tags-list">
                        <?php foreach ($projectTags as $tag): ?>
                            <span class="tag"><?= htmlspecialchars($tag) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="detail-actions">
                <?php if (!empty($projectGithub)): ?>
                    <a href="<?= htmlspecialchars($projectGithub) ?>" target="_blank" rel="noopener noreferrer" class="btn-github">
                        Voir sur GitHub
                    </a>
                <?php endif; ?>

                <?php if (!empty($projectDemo)): ?>
                    <a href="<?= htmlspecialchars($projectDemo) ?>" target="_blank" rel="noopener noreferrer" class="btn-demo">
                        Voir la démo
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>