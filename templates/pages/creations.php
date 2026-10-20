<?php
/**
 * Fichier : templates/pages/creations.php
 * Rôle : Vue globale de la galerie des créations (100% muette, affichage direct).
 */

if (!defined('APP_STARTED')) {
    die('Accès direct interdit');
}
?>

<div class="creations-page container">
    <div class="creations-header">
        <h1><?= htmlspecialchars($creationsHeaderTitle) ?></h1>
        <p><?= htmlspecialchars($creationsHeaderSubtitle) ?></p>

        <!-- Filtres rapides -->
        <?php if (!empty($creationsFilters)): ?>
            <div class="creations-filters">
                <?php foreach ($creationsFilters as $filterKey => $filterLabel): ?>
                    <button class="filter-btn <?= $filterKey === 'all' ? 'active' : '' ?>" data-filter="<?= htmlspecialchars($filterKey) ?>">
                        <?= htmlspecialchars($filterLabel) ?>
                    </button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="creations-grid">
        <?php if (!empty($preparedCreations)): ?>
            <?php foreach ($preparedCreations as $creation): ?>
                <div class="creation-card" data-category="<?= htmlspecialchars($creation['category']) ?>">
                    <?php if (!empty($creation['image'])): ?>
                        <div class="card-image">
                            <img src="<?= htmlspecialchars($creation['image']) ?>" alt="<?= htmlspecialchars($creation['title']) ?>">
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
                        <h2><?= htmlspecialchars($creation['title']) ?></h2>
                        <p><?= htmlspecialchars($creation['shortDescription']) ?></p>
                        
                        <?php if (!empty($creation['tags'])): ?>
                            <div class="tags-list">
                                <?php foreach ($creation['tags'] as $tag): ?>
                                    <span class="tag"><?= htmlspecialchars($tag) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <a href="<?= htmlspecialchars($creation['detailUrl']) ?>" class="btn-details">
                            <?= htmlspecialchars($btnDetailsText) ?>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="no-creations"><?= htmlspecialchars($noCreationsText) ?></p>
        <?php endif; ?>
    </div>
</div>