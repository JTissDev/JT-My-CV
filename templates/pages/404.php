<!-- 
  Fichier : templates/pages/404.php 
  Rôle : Affichage dynamique de la page d'erreur 404[cite: 9]
-->
<main class="error-404">
    <div class="error-404__container">
        <span class="error-404__code"><?= htmlspecialchars($code) ?></span>
        <h1 class="error-404__title"><?= htmlspecialchars($title) ?></h1>
        <p class="error-404__subtitle"><?= htmlspecialchars($subtitle) ?></p>
        <p class="error-404__message"><?= htmlspecialchars($message) ?></p>
        
        <!-- Utilisation de la variable sécurisée $currentLang[cite: 9] -->
        <a href="index.php?page=home&lang=<?= htmlspecialchars($currentLang) ?>" class="error-404__button">
            <?= htmlspecialchars($buttonText) ?>
        </a>
    </div>
</main>