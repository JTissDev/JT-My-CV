<main id="home-page" class="page-container">

    
        <div class="home-hero__container">
            <h1 class="home-hero__name"><?= htmlspecialchars($data->header->name ?? 'Julien Tissier') ?></h1>
            <h2 class="home-hero__job"><?= htmlspecialchars($data->header->title ?? 'Développeur Web') ?></h2>
</div>
<?= $sitePresComponent->render() ?>

<?= $heroPresComponent->render() ?>
           
<div>
            <a href="<?= getCleanUrl('creations', $lang ?? 'fr') ?>" class="home-hero__cta">
                <?= htmlspecialchars($ctaText) ?>
            </a>
        </div>
    

    <!-- Section Compétences -->
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