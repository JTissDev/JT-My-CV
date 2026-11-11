<?php
if (!defined('APP_STARTED')) {
    http_response_code(403);
    exit('Accès direct interdit.');
}
/** @var list<array<string,mixed>> $formattedRows Préparé par careerController.php */
$e = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<main class="page-parcours">
    <div class="page-parcours__container">
        <h1 class="page-parcours__title"><?= $e($careerPageTitle) ?></h1>

        <?php if ($formattedRows === []): ?>
            <p class="page-parcours__empty"><?= $e($careerEmptyMessage) ?></p>
        <?php else: ?>
            <ol class="timeline" aria-label="<?= $e($careerPageTitle) ?>">
                <?php foreach ($formattedRows as $row): ?>
                    <?php require $row['partialPath']; ?>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </div>
</main>
