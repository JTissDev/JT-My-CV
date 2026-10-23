<main id="home-page" class="page-container">

    <?php include __DIR__ . '/../components/page-title.php'; ?>

    <?= $sitePresComponent->render() ?>

    <?= $heroPresComponent->render() ?>

    <div class="home-actions">
        <?=  $careerButton->render() ?>
        <?=  $creationButton->render() ?>
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