<?php
/**
 * Fichier : templates/pages/404.php
 * Rôle : Affichage de la page d'erreur 404.
 * Variables préparées par src/controllers/404Controller.php : $code, $title, $subtitle, $message, $buttonText, $homeUrl.
 */
?>
<main class="error-404">
    <div class="error-404__container">
        <span class="error-404__code"><?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?></span>
        <h1 class="error-404__title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="error-404__subtitle"><?= htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8') ?></p>
        <p class="error-404__message"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>

        <a href="<?= htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') ?>" class="error-404__button">
            <?= htmlspecialchars($buttonText, ENT_QUOTES, 'UTF-8') ?>
        </a>
    </div>
</main>
