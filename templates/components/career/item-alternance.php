<?php
if (!defined('APP_STARTED')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}
?>
<li class="timeline-item <?= $e($row['classes']) ?>">
    <span class="timeline-item__marker" aria-hidden="true"></span>
    <article class="timeline-item__content">
        <div class="timeline-item__header-meta">
            <span class="timeline-item__date"><?= $e($row['dateLabel']) ?></span>
            <span class="timeline-item__badge"><?= $e($row['typeLabel']) ?></span>
        </div>
        <h2 class="timeline-item__heading"><?= $e($row['title']) ?></h2>
        <div class="alternance-card__facets">
            <?php foreach ($row['facets'] as $facet): ?>
                <section class="alternance-card__facet alternance-card__facet--<?= $e($facet['kind']) ?>">
                    <h3><?= $e($facet['heading']) ?></h3>
                    <p class="alternance-card__role"><?= $e($facet['title']) ?></p>
                    <?php if ($facet['place'] !== ''): ?>
                        <p class="alternance-card__place"><?= $e($facet['place']) ?></p>
                    <?php endif; ?>
                    <div class="timeline-item__description">
                        <?php foreach ($facet['description'] as $paragraph): ?>
                            <p><?= $e($paragraph) ?></p>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($facet['details'] !== null): ?>
                        <details class="timeline-item__details" id="<?= $e($facet['detailsId']) ?>">
                            <summary><?= $e($row['detailsLabel']) ?></summary>
                            <div class="timeline-item__long-description">
                                <?php foreach ($facet['details'] as $paragraph): ?>
                                    <p><?= $e($paragraph) ?></p>
                                <?php endforeach; ?>
                            </div>
                        </details>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>
        </div>
        <?php // lienInfo agrégé n'est qu'un booléen de mise en évidence; aucun lien externe n'est défini dans le modèle. ?>
    </article>
</li>
