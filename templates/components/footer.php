<footer class="site-footer">
    <div class="site-footer__container">
        <!-- Section Copyright -->
        <p class="site-footer__copyright">
            &copy; <?= $currentYear ?> Julien Tissier. <?= htmlspecialchars($copyrightText) ?>
        </p>
        
        <!-- 1. Section Réseaux Sociaux -->
        <div class="site-footer__socials">
            <a href="<?= $links->linkedin ?>" target="_blank" rel="noopener noreferrer" class="site-footer__social-link" aria-label="LinkedIn">
                <!-- SVG LinkedIn ou icône -->
                <img src="./assets/images/linkedin-icon.svg" alt="LinkedIn" class="icon" />
            </a>
            <a href="<?= $links->github ?>" target="_blank" rel="noopener noreferrer" class="site-footer__social-link" aria-label="GitHub">
                <!-- SVG GitHub -->
                <img src="./assets/images/github-icon.svg" alt="GitHub" class="icon" />
            </a>
            <a href="<?= $links->codepen ?>" target="_blank" rel="noopener noreferrer" class="site-footer__social-link" aria-label="CodePen">
                <!-- SVG CodePen -->
                <img src="https://assets.codepen.io/t-1/codepen-logo.svg" alt="CodePen Logo as SVG" />
            </a>
            <a href="<?= $links->codeur ?>" target="_blank" rel="noopener noreferrer" class="site-footer__social-link" aria-label="Codeur.com">
                <!-- SVG Codeur.com ou icône générique / badge -->
                <img src="./assets/images/codeur-icon.jpeg" alt="Codeur.com" class="icon" />
            </a>
        </div>

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

