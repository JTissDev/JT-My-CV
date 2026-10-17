<header class="site-header">
    <div class="site-header__container">

        <!-- Identité -->
        <div class="site-header__brand">
            <a href="index.php?page=home&lang=<?= htmlspecialchars($currentLang) ?>">
               <img src="./assets/images/jt-logo.svg" alt="<?= htmlspecialchars($texte_alt); ?>" />
            </a>
        </div>

        <!-- Navigation -->
        <nav class="site-header__nav">
            <?php foreach ($navItems as $pageKey => $pageLabel): ?>
                <a href="index.php?page=<?= htmlspecialchars($pageKey) ?>&lang=<?= htmlspecialchars($currentLang) ?>"
                   class="site-header__link <?= ($currentPage === $pageKey) ? 'is-active' : '' ?>">
                    <?= htmlspecialchars($pageLabel) ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <!-- Sélecteur de langue -->
        <div class="site-header__lang">
            <a href="index.php?page=<?= htmlspecialchars($currentPage) ?>&lang=<?= htmlspecialchars($targetLang) ?>"
                class="site-header__lang-btn"
                title="Changer de langue">
                <?= htmlspecialchars($langButtonText) ?>
            </a>
        </div>

    </div>
</header>