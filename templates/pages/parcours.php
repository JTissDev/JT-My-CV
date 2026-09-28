<main class="page-parcours">
    <div class="page-parcours__container">
        <h1 class="page-parcours__title">
            <?= $currentLang === 'en' ? 'My Professional & Educational Journey' : 'Mon Parcours' ?>
        </h1>

        <div class="timeline">
            <?php foreach ($parcoursItems as $item): ?>
                <?php 
                    // Identification du type pour appliquer des classes CSS et libellés spécifiques
                    $typeClass = '';
                    $typeLabel = '';
                    $locationInfo = '';

                    if ($item instanceof Experience) {
                        $typeClass = 'timeline-item--experience';
                        $typeLabel = $currentLang === 'en' ? 'Experience' : 'Expérience';
                        $locationInfo = $item->getEntreprise() . ' — ' . $item->getVille() . ' (' . $item->getDept() . ')';
                    } elseif ($item instanceof Etude) {
                        $typeClass = 'timeline-item--etude';
                        $typeLabel = $currentLang === 'en' ? 'Education' : 'Étude / Formation';
                        $locationInfo = $item->getEtablissement() . ' — ' . $item->getVille() . ' (' . $item->getDept() . ')';
                    } else {
                        $typeClass = 'timeline-item--projet';
                        $typeLabel = $currentLang === 'en' ? 'Project' : 'Projet Personnel';
                        $locationInfo = $item->getVille() . ' (' . $item->getDept() . ')';
                    }
                ?>
                
                <div class="timeline-item <?= $typeClass ?>">
                    <div class="timeline-item__marker"></div>
                    <div class="timeline-item__content">
                        <div class="timeline-item__header-meta">
                            <span class="timeline-item__date">
                                <?= htmlspecialchars($item->getDateDebut()) ?> - <?= htmlspecialchars($item->getDateFin()) ?>
                            </span>
                            <span class="timeline-item__badge"><?= htmlspecialchars($typeLabel) ?></span>
                        </div>

                        <h3 class="timeline-item__heading"><?= htmlspecialchars($item->getTitre()) ?></h3>
                        
                        <?php if (!empty($locationInfo)): ?>
                            <p class="timeline-item__subheading"><?= htmlspecialchars($locationInfo) ?></p>
                        <?php endif; ?>

                        <div class="timeline-item__description">
                            <?php foreach ($item->getDescription() as $paragraph): ?>
                                <p><?= htmlspecialchars($paragraph) ?></p>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</main>