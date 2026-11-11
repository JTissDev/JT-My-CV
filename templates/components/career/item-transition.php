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
        </div>
        <h2 class="timeline-item__heading"><?= $e($row['title']) ?></h2>
        <div class="timeline-item__description">
            <?php foreach ($row['description'] as $paragraph): ?>
                <p><?= $e($paragraph) ?></p>
            <?php endforeach; ?>
        </div>
    </article>
</li>
