<?php
/**
 * Main view template assembling partial view components.
 * 
 * @author    J.Tiss <jtissdev@gmail.com>
 * @since     1.0.0
 * @version   1.4.0
 */
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang ?? 'fr') ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CV - <?= htmlspecialchars($headerData['nom']) ?> (<?= htmlspecialchars(ucfirst($profilCible)) ?>)</title>
    <link rel="stylesheet" href="styles/cv_a4_default.css">
</head>

<body>
    <div class="page-a4">
        <?php require_once __DIR__ . '/partials/header.php'; ?>

        <div class="cv-body">
            <?php require_once __DIR__ . '/partials/sidebar.php'; ?>
            <?php require_once __DIR__ . '/partials/main.php'; ?>
        </div>
    </div>
</body>
</html>