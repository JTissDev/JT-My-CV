<div class="page-titles">
    <h1 class="page-title"><?= htmlspecialchars($pagedata->title) ?></h1>
    <?php if (!empty($pagedata->subtitle)) : ?>
        <h2 class="page-subTitle"><?= htmlspecialchars($pagedata->subtitle) ?></h2>
    <?php endif; ?>
</div>