<header class="site-header">
    <div class="site-header__container">

        <!-- Identité -->
        <div class="site-header__brand">
        <a href="<?= getCleanUrl('home', $currentLang) ?>">
            <img src="./assets/images/jt-logo.svg" alt="<?= htmlspecialchars($data->header->logo_alt ?? 'Logo J.Tissier'); ?>" />
        </a>
    </div>

    <!-- Navigation -->
    <nav class="site-header__nav">
        <?php foreach ($navItems as $pageKey => $pageLabel): ?>
            <a href="<?= getCleanUrl($pageKey, $currentLang) ?>" class="site-header__link <?= ($currentPage === $pageKey) ? 'is-active' : '' ?>">
                <?= htmlspecialchars($pageLabel) ?>
            </a>
        <?php endforeach; ?>
    </nav>

        <!-- Sélecteur de langue -->
        <div class="site-header__lang">
            <a href="<?= getCleanUrl($currentPage, $targetLang) ?>" class="site-header__lang-btn">
                <?= htmlspecialchars($langButtonText) ?>
            </a>
        </div>

    </div>
</header>