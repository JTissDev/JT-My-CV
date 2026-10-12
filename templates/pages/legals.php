<main class="page-legals container">

    <div class="legal-header">
        <h1><?= htmlspecialchars($pageTitle) ?></h1>
    </div>

    <div class="legal-content">
        <?php if (!empty($sections)): ?>
            <?php foreach ($sections as $section): ?>
                <section class="legal-block">
                    <h2><?= htmlspecialchars($section->subtitle) ?></h2>

                    <?php if (isset($section->content) && is_array($section->content)): ?>
                        <?php foreach ($section->content as $paragraph): ?>
                            <p><?= htmlspecialchars($paragraph) ?></p>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>Contenu indisponible pour cette section.</p>
                    <?php endif; ?>

                </section>
            <?php endforeach; ?>
        <?php else: ?>
            <p>Le contenu de cette page est en cours de rédaction.</p>
        <?php endif; ?>
    </div>
</main>