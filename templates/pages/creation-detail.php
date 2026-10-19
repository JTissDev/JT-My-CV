<?php
/**
 * Fichier : templates/pages/creation-detail.php
 * Rôle : Vue dédiée à l'affichage détaillé d'une création spécifique.
 */

if (!defined('APP_STARTED')) {
    die('Accès direct interdit');
}

$pageStrings     = $data->pages->creations ?? null;
$btnBack         = $pageStrings->buttons->back ?? "← Retour à la liste des créations";
$btnGithub       = $pageStrings->buttons->github ?? "Voir sur GitHub";
$btnDemo         = $pageStrings->buttons->demo ?? "Démo Live";
$lblTech         = $pageStrings->labels->technologies ?? "Technologies & Tags";
$lblDesc         = $pageStrings->labels->long_description ?? "Description détaillée";
$currentPageSlug = $_GET['page'] ?? 'creations';
?>

<div class="creations-page container">
    <div class="creation-detail">
        <a href="?page=<?= htmlspecialchars($currentPageSlug) ?>" class="btn-back">
            <?= htmlspecialchars($btnBack) ?>
        </a>

        <div class="detail-header">
            <?php if (!empty($currentCreation['category'])): ?>
                <span class="badge-category"><?= htmlspecialchars($currentCreation['category']) ?></span>
            <?php endif; ?>
            <h1><?= htmlspecialchars($currentCreation['title'] ?? '') ?></h1>
            <?php if (!empty($currentCreation['year'])): ?>
                <span class="project-year">Année : <?= htmlspecialchars($currentCreation['year']) ?></span>
            <?php endif; ?>
        </div>

        <?php if (!empty($currentCreation['image'])): ?>
            <div class="detail-media">
                <img src="<?= htmlspecialchars($currentCreation['image']) ?>" alt="<?= htmlspecialchars($currentCreation['title'] ?? '') ?>">
            </div>
        <?php endif; ?>

        <div class="detail-content">
            <h2><?= htmlspecialchars($lblDesc) ?></h2>
            <div class="description-paragraphs">
                <?php 
                $longDesc = $currentCreation['longDescription'] ?? [];
                if (is_array($longDesc)) {
                    foreach ($longDesc as $paragraph) {
                        echo '<p>' . htmlspecialchars($paragraph) . '</p>';
                    }
                } else {
                    echo '<p>' . htmlspecialchars($longDesc) . '</p>';
                }
                ?>
            </div>

            <?php if (!empty($currentCreation['tags'])): ?>
                <div class="tags-section">
                    <h3><?= htmlspecialchars($lblTech) ?></h3>
                    <div class="tags-list">
                        <?php foreach ($currentCreation['tags'] as $tag): ?>
                            <span class="tag"><?= htmlspecialchars($tag) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="detail-actions">
                <?php if (!empty($currentCreation['githubUrl'])): ?>
                    <a href="<?= htmlspecialchars($currentCreation['githubUrl']) ?>" target="_blank" class="btn-github">
                        <?= htmlspecialchars($btnGithub) ?>
                    </a>
                <?php endif; ?>
                <?php if (!empty($currentCreation['demoUrl'])): ?>
                    <a href="<?= htmlspecialchars($currentCreation['demoUrl']) ?>" target="_blank" class="btn-demo">
                        <?= htmlspecialchars($btnDemo) ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>