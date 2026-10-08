<?php
/**
 * Partial view for Main Content (Career history & Education).
 * 
 * @author    J.Tiss <jtissdev@gmail.com>
 * @since     1.0.0
 * @version   1.0.0
 */
?>
<main class="main-content">
    <section class="section-main">
        <h3 class="titre-section-main"><?= htmlspecialchars($labels->career ?? 'Parcours Professionnel & Formations') ?></h3>

        <?php if (!empty($career)): ?>
            <?php foreach ($career as $item): ?>
                <?php 
                $cssClasses = "career-item cat-" . $item['category'];
                if (!$item['isRelevant']) {
                    $cssClasses .= " career-reduced";
                }
                ?>
                <article class="<?= htmlspecialchars($cssClasses) ?>">
                    <div class="career-header">
                        <span class="career-dates">
                            <?= htmlspecialchars($item['dateDebut']) ?> - <?= htmlspecialchars($item['dateFin']) ?>
                        </span>
                        <h4 class="career-title"><?= htmlspecialchars($item['titre']) ?></h4>
                    </div>

                    <div class="career-subinfo">
                        <span class="career-org"><?= htmlspecialchars($item['entreprise']) ?></span>
                        <?php if (!empty($item['ville'])): ?>
                            <span class="career-location">
                                - <?= htmlspecialchars($item['ville']) ?><?= !empty($item['dept']) ? ' (' . $item['dept'] . ')' : '' ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($item['description'])): ?>
                        <ul class="career-desc">
                            <?php foreach ($item['description'] as $ligneDesc): ?>
                                <li><?= htmlspecialchars($ligneDesc) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</main>