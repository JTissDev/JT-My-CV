<?php
/**
 * Fichier : templates/pages/creations.php
 * Rôle : Vue globale de la galerie des créations (Grille + Filtres).
 */

if (!defined('APP_STARTED')) {
    die('Accès direct interdit');
}

$pageStrings     = $data->pages->creations ?? null;
$headerTitle     = $pageStrings->header_title ?? "Mes Créations & Réalisations";
$headerSubtitle  = $pageStrings->header_subtitle ?? "Découvrez une sélection de mes projets techniques.";
$btnDetails      = $pageStrings->buttons->details ?? "Voir le projet →";
$filters         = $pageStrings->filters ?? (object)['all' => 'Tous', 'devops' => 'DevOps / Bash', 'web' => 'Web'];
$currentPageSlug = $_GET['page'] ?? 'creations';
?>

<div class="creations-page container">
    <div class="creations-header">
        <h1><?= htmlspecialchars($headerTitle) ?></h1>
        <p><?= htmlspecialchars($headerSubtitle) ?></p>

        <!-- Filtres rapides -->
        <div class="creations-filters">
            <?php foreach ($filters as $filterKey => $filterLabel): ?>
                <button class="filter-btn <?= $filterKey === 'all' ? 'active' : '' ?>" data-filter="<?= htmlspecialchars($filterKey) ?>">
                    <?= htmlspecialchars($filterLabel) ?>
                </button>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="creations-grid">
        <?php if (!empty($creations)): ?>
            <?php foreach ($creations as $creation): ?>
                <div class="creation-card" data-category="<?= htmlspecialchars($creation['category'] ?? '') ?>">
                    <?php if (!empty($creation['image'])): ?>
                        <div class="card-image">
                            <img src="<?= htmlspecialchars($creation['image']) ?>" alt="<?= htmlspecialchars($creation['title'] ?? '') ?>">
                        </div>
                    <?php endif; ?>
                    <div class="card-body">
                        <div class="card-meta">
                            <?php if (!empty($creation['category'])): ?>
                                <span class="badge-category"><?= htmlspecialchars($creation['category']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($creation['year'])): ?>
                                <span class="card-year"><?= htmlspecialchars($creation['year']) ?></span>
                            <?php endif; ?>
                        </div>
                        <h2><?= htmlspecialchars($creation['title'] ?? '') ?></h2>
                        <p><?= htmlspecialchars($creation['shortDescription'] ?? '') ?></p>
                        
                        <?php if (!empty($creation['tags'])): ?>
                            <div class="tags-list">
                                <?php foreach ($creation['tags'] as $tag): ?>
                                    <span class="tag"><?= htmlspecialchars($tag) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <a href="?page=<?= htmlspecialchars($currentPageSlug) ?>&creation=<?= htmlspecialchars($creation['id'] ?? '') ?>" class="btn-details">
                            <?= htmlspecialchars($btnDetails) ?>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="no-creations">Aucune création pour le moment.</p>
        <?php endif; ?>
    </div>
</div>