<?php
/**
 * Partial view for CV Header.
 * 
 * @author    J.Tiss <jtissdev@gmail.com>
 * @since     1.0.0
 * @version   1.0.0
 */
?>
<header class="cv-header">
    <div class="header-main">
        <h1 class="nom"><?= htmlspecialchars($headerData['nom']) ?></h1>
        <h2 class="titre-profil"><?= htmlspecialchars($headerData['profil']) ?></h2>
    </div>
</header>