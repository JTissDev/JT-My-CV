<?php
/**
 * Default A4 CV template view.
 * Displays personal details, sidebar, and main career history.
 * 
 * @author    J.Tiss <jtissdev@gmail.com>
 * @since     1.0.0
 * @version   1.3.1
 */
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang ?? 'fr') ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CV - <?= htmlspecialchars($cvLangData->header->name ?? 'Julien Tissier') ?> (<?= htmlspecialchars(ucfirst($profilCible)) ?>)</title>
    <link rel="stylesheet" href="styles/cv_a4_default.css">
</head>

<body>
    <div class="page-a4">
        <header class="cv-header">
            <div class="header-main">
                <h1 class="nom"><?= htmlspecialchars($cvLangData->header->name ?? 'JULIEN TISSIER') ?></h1>
                <h2 class="titre-profil"><?= htmlspecialchars($cvLangData->print->profil_title->$profilCible ?? 'Développeur') ?></h2>
            </div>
        </header>

        <div class="cv-body">
            <aside class="sidebar">
                <!-- Section Contact -->
                <section id="contact" class="sidebar-section">
                    <h3 class="sidebar_section_title"><?= htmlspecialchars($cvLangData->print->labels->contact ?? 'Contact') ?></h3>
                    <ul id="contact_list">
                        <?php foreach ($registreData->contacts as $key => $data): ?>
                            <li id="<?= htmlspecialchars($key) ?>" class="contactelement">
                                <strong><?= htmlspecialchars($cvLangData->contact->$key->label ?? $key) ?> : </strong>
                                <?php if ($key === 'adress'): ?>
                                    <?= htmlspecialchars(($data->street->value ?? '') . ' - ' . ($data->zip->value ?? '') . ' ' . ($data->city->value ?? '')) ?>
                                <?php else: ?>
                                    <?= htmlspecialchars($data->value ?? '') ?>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>

                <!-- Section Compétences -->
                <section id="skills" class="sidebar-section">
                    <h3 class="sidebar_section_title"><?= htmlspecialchars($cvLangData->print->labels->competences ?? 'Compétences') ?></h3>
                    <?php
                    $competencesGrouped = [];
                    if (isset($registreData->skills)) {
                        foreach ($registreData->skills as $key => $comp) {
                            $domaines = $comp->domaines ?? [];
                            if (in_array($profilCible, $domaines) || in_array('commun', $domaines)) {
                                $type = $comp->type ?? 'autres';
                                $competencesGrouped[$type][] = ['key' => $key, 'data' => $comp];
                            }
                        }
                    }
                    ?>
                    <div id="skills_list">
                        <?php foreach ($competencesGrouped as $type => $listeComps): ?>
                            <div class="skill-group">
                                <h4 class="skill-type-title"><?= htmlspecialchars($cvLangData->print->labels->$type ?? ucfirst($type)) ?></h4>
                                <ul class="skill-type-list">
                                    <?php foreach ($listeComps as $item): ?>
                                        <?php $skillKey = $item['key']; ?>
                                        <li id="<?= htmlspecialchars($skillKey) ?>" class="skill-element">
                                            <?= htmlspecialchars($cvLangData->skills->$skillKey->name ?? $skillKey) ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <!-- Section Certifications -->
                <section id="certifications" class="sidebar-section">
                    <h3 class="sidebar_section_title"><?= htmlspecialchars($cvLangData->print->labels->certifications ?? 'Certifications') ?></h3>
                    <ul id="certifications_list">
                        <?php if (isset($registreData->certifications)): ?>
                            <?php foreach ($registreData->certifications as $key => $certif): ?>
                                <?php $domaines = $certif->domaines ?? []; ?>
                                <?php if (in_array($profilCible, $domaines) || in_array('commun', $domaines)): ?>
                                    <li id="<?= htmlspecialchars($key) ?>" class="certification-element">
                                        <?= htmlspecialchars($cvLangData->certifications->$key->name ?? $key) ?>
                                    </li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </section>

                <!-- Section Langues -->
                <section id="langues" class="sidebar-section">
                    <h3 class="sidebar_section_title"><?= htmlspecialchars($cvLangData->print->labels->langues ?? 'Langues') ?></h3>
                    <ul id="langues_list">
                        <?php if (isset($registreData->languages)): ?>
                            <?php foreach ($registreData->languages as $langKey => $langData): ?>
                                <?php
                                $langName = $cvLangData->languages->names->$langKey ?? ucfirst($langKey);
                                $oralText = $cvLangData->languages->levels->{$langData->oral_level ?? ''} ?? ($langData->oral_level ?? '');
                                $writtenText = $cvLangData->languages->levels->{$langData->written_level ?? ''} ?? ($langData->written_level ?? '');
                                ?>
                                <li id="<?= htmlspecialchars($langKey) ?>" class="langue-element">
                                    <strong><?= htmlspecialchars($langName) ?> :</strong>
                                    <span>Oral : <?= htmlspecialchars($oralText) ?> / Écrit : <?= htmlspecialchars($writtenText) ?></span>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </section>
            </aside>

            <main class="main-content">
                <section class="section-main">
                    <h3 class="titre-section-main"><?= htmlspecialchars($cvLangData->print->labels->career ?? 'Parcours Professionnel & Formations') ?></h3>

                    <?php if (isset($career) && is_array($career)): ?>
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
                                            - <?= htmlspecialchars($item['ville']) ?><?= isset($item['dept']) ? ' (' . $item['dept'] . ')' : '' ?>
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
        </div>
    </div>
</body>
</html>