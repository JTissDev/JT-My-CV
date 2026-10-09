<main class="page-parcours">
    <div class="page-parcours__container">
        <h1 class="page-parcours__title">
            <?= htmlspecialchars($data->pages->career->page_title ?? "default :Mon Parcours"); ?>
        </h1>

        <div class="timeline">
            <?php foreach ($formattedRows as $rowItems): ?>
                
                <div class="timeline-row <?= count($rowItems) > 1 ? 'timeline-row--grouped' : '' ?>">
                    
                    <?php foreach ($rowItems as $data): ?>
                        <?php 
                            // On extrait l'objet $item pour raccourcir l'écriture des getters
                            $item = $data['item']; 
                        ?>
                        
                        <div class="timeline-item ">
                            <div class="timeline-item__marker"></div>

                            <!-- Injection directe des classes CSS préparées -->
                            <div class="timeline-item__content <?= htmlspecialchars($data['typeClass']) ?> <?= htmlspecialchars($data['sideClass']) ?>">
                                <div class="timeline-item__header-meta">
                                    <span class="timeline-item__date">
                                        <?= htmlspecialchars($item->getDateDebut()) ?> - <?= htmlspecialchars($item->getDateFin()) ?>
                                    </span>
                                    <span class="timeline-item__badge"><?= htmlspecialchars($data['typeLabel']) ?></span>
                                </div>

                                <h3 class="timeline-item__heading"><?= htmlspecialchars($item->getTitre()) ?></h3>
                                
                                <!-- Affichage conditionnel simplifié -->
                                <?php if (!empty($data['locationInfo'])): ?>
                                    <p class="timeline-item__subheading"><?= htmlspecialchars($data['locationInfo']) ?></p>
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
            <?php endforeach; ?>
        </div>
    </div>
</main>