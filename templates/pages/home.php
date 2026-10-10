<main id="home-page" class="pages">
    
    <section class="home-hero">
        <div class="home-hero__container">
            <span class="home-hero__greeting"><?= htmlspecialchars($greeting) ?></span>
            <h1 class="home-hero__name"><?= htmlspecialchars($data->header->name ?? 'Julien Tissier') ?></h1>
            <h2 class="home-hero__job"><?= htmlspecialchars($data->header->title ?? 'Développeur Web') ?></h2>
            
            <!-- Nouvelle section de présentation dynamique -->
            <div class="home-hero__presentation">
                
                <!-- Boucle pour l'introduction -->
                <?php foreach ($introData as $paragraph): ?>
                    <p><?= htmlspecialchars($paragraph) ?></p>
                <?php endforeach; ?>

                <!-- Boucle pour la liste des langages appris -->
                <?php if (!empty($langsData)): ?>
                    <ul class="home-hero__list">
                        <?php foreach ($langsData as $langItem): ?>
                            <li>
                                <strong><?= htmlspecialchars($langItem->name) ?> :</strong> 
                                <?= htmlspecialchars($langItem->detail) ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <!-- Boucle pour la conclusion -->
                <?php foreach ($outroData as $paragraph): ?>
                    <p><?= htmlspecialchars($paragraph) ?></p>
                <?php endforeach; ?>

            </div>
            
            <a href="index.php?page=creations&lang=<?= htmlspecialchars($lang ?? 'fr') ?>" class="home-hero__cta">
                <?= htmlspecialchars($ctaText) ?>
            </a>
        </div>
    </section>

    <!-- Section Compétences (inchangée) -->
    <section class="home-skills">
        <div class="home-skills__container">
            <h3 class="home-skills__title"><?= htmlspecialchars($skillsTitle) ?></h3>
            <ul class="home-skills__list">
                <?php foreach ($skillsList as $skill): ?>
                    <li class="home-skills__item"><?= htmlspecialchars($skill) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>

</main>