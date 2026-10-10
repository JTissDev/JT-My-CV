<main class="page-legals container">
    <div class="legals-header">
        <h1><?= htmlspecialchars($pageTitle) ?></h1>
    </div>

    <div class="legals-content">
        <?php if (!empty($sections)): ?>
            <?php foreach ($sections as $section): ?>
                <section class="legal-block">
                    <h2><?= htmlspecialchars($section['subtitle']) ?></h2>
                    
                    <!-- On boucle sur le tableau de contenu pour créer un paragraphe par ligne -->
                    <?php if (isset($section['content']) && is_array($section['content'])): ?>
                        <?php foreach ($section['content'] as $paragraph): ?>
                            <p><?= htmlspecialchars($paragraph) ?></p>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                </section>
            <?php endforeach; ?>
        <?php else: ?>
            <p>Les mentions légales sont en cours de rédaction.</p>
        <?php endif; ?>
    </div>
</main>