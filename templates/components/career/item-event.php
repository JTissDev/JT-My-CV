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
        <?php if ($row['locationInfo'] !== ''): ?>
            <p class="timeline-item__subheading"><?= $e($row['locationInfo']) ?></p>
        <?php endif; ?>
        <div class="timeline-item__description">
            <?php foreach ($row['description'] as $paragraph): ?>
                <p><?= $e($paragraph) ?></p>
            <?php endforeach; ?>
        </div>
        <?php if ($row['details'] !== null): ?>
            <details class="timeline-item__details" id="<?= $e($row['detailsId']) ?>">
                <summary><?= $e($row['detailsLabel']) ?></summary>
                <div class="timeline-item__long-description">
                    <?php foreach ($row['details'] as $paragraph): ?>
                        <p><?= $e($paragraph) ?></p>
                    <?php endforeach; ?>
                </div>
            </details>
        <?php endif; ?>
        <?php // lienInfo est un booléen de mise en valeur, pas une URL : aucun href n'est inventé. ?>
    </article>
</li>
