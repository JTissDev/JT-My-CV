<footer class="site-footer">
    <div class="site-footer__container">
        <!-- Section Copyright -->
        <p class="site-footer__copyright">
            &copy; <?= $currentYear ?> Julien Tissier. <?= htmlspecialchars($copyrightText) ?>
        </p>
        
        <!-- Navigation réglementaire -->
        <nav class="site-footer__nav">
            <!-- Note l'utilisation de ?page=...&lang=... pour garder le routage actif -->
            <a href="<?= getCleanUrl('legals', $currentLang) ?>">
                <?= htmlspecialchars($legalNotice) ?>
            </a>
            <a href="<?= getCleanUrl('confidentialite', $currentLang) ?>">
                <?= htmlspecialchars($privacyPolicy) ?>
            </a>
            <a href="<?= getCleanUrl('cgu-cgv', $currentLang) ?>">
                <?= htmlspecialchars($terms) ?>
            </a>
            <a href="<?= getCleanUrl('contactUs', $currentLang) ?>">
                <?= htmlspecialchars($contact) ?>
            </a>
        </nav>
    </div>
</footer>

