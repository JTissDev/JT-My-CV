<footer class="site-footer">
    <div class="site-footer__container">
        <!-- Section Copyright -->
        <p class="site-footer__copyright">
            &copy; <?= $currentYear ?> Julien Tissier. <?= htmlspecialchars($copyrightText) ?>
        </p>
        
        <!-- Navigation réglementaire -->
        <nav class="site-footer__nav">
            <!-- Note l'utilisation de ?page=...&lang=... pour garder le routage actif -->
            <a href="index.php?page=mentions-legales&lang=<?= htmlspecialchars($currentLang) ?>">
                <?= htmlspecialchars($legalNotice) ?>
            </a>
            <a href="index.php?page=confidentialite&lang=<?= htmlspecialchars($currentLang) ?>">
                <?= htmlspecialchars($privacyPolicy) ?>
            </a>
            <a href="index.php?page=cgu-cgv&lang=<?= htmlspecialchars($currentLang) ?>">
                <?= htmlspecialchars($terms) ?>
            </a>
            <a href="index.php?page=contact&lang=<?= htmlspecialchars($currentLang) ?>">
                <?= htmlspecialchars($contact) ?>
            </a>
        </nav>
    </div>
</footer>

