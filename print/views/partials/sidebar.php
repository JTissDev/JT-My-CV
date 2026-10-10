<?php
/**
 * Partial view for Sidebar sections (Contact, Skills, Certifications, Languages).
 * 
 * @author    J.Tiss <jtissdev@gmail.com>
 * @since     1.0.0
 * @version   1.0.0
 */
?>
<aside class="sidebar">
    <!-- Contact -->
    <section id="contact" class="sidebar-section">
        <h3 class="sidebar_section_title"><?= htmlspecialchars($labels->contact ?? 'Contact') ?></h3>
        <ul id="contact_list">
            <?php foreach ($contacts as $contact): ?>
                <li id="<?= htmlspecialchars($contact['key']) ?>" class="contactelement">
                    <strong><?= htmlspecialchars($contact['label']) ?> : </strong>
                    <?= htmlspecialchars($contact['value']) ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>


    <!-- Compétences -->
    <section id="skills" class="sidebar-section">
        <h3 class="sidebar_section_title"><?= htmlspecialchars($labels->competences ?? 'Compétences') ?></h3>
        <div id="skills_list">
            <?php foreach ($skillsGrouped as $typeKey => $group): ?>
                <div class="skill-group">
                    
                    <ul class="skill-type-list">
                        <?php foreach ($group['items'] as $skill): ?>
                            <li id="<?= htmlspecialchars($skill['key']) ?>" class="skill-element">
                                <?= htmlspecialchars($skill['name']) ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Certifications -->
    <?php if (!empty($certifications)): ?>
        <section id="certifications" class="sidebar-section">
            <h3 class="sidebar_section_title"><?= htmlspecialchars($labels->certifications ?? 'Certifications') ?></h3>
            <ul id="certifications_list">
                <?php foreach ($certifications as $cert): ?>
                    <li id="<?= htmlspecialchars($cert['key']) ?>" class="certification-element">
                        <?= htmlspecialchars($cert['name']) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <!-- Langues -->
    <?php if (!empty($languages)): ?>
        <section id="langues" class="sidebar-section">
            <h3 class="sidebar_section_title"><?= htmlspecialchars($labels->langues ?? 'Langues') ?></h3>
            <ul id="langues_list">
                <?php foreach ($languages as $langItem): ?>
                    <li id="<?= htmlspecialchars($langItem['key']) ?>" class="langue-element">
                        <strong><?= htmlspecialchars($langItem['name']) ?> :</strong>
                        <span>Oral : <?= htmlspecialchars($langItem['oral']) ?> / Écrit : <?= htmlspecialchars($langItem['written']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <!-- Centres d'intérêt -->
    <section id="interets" class="sidebar-section">
        <h3 class="sidebar_section_title"><?= htmlspecialchars($labels->interets ?? "Centres d'intérêt") ?></h3>
        <ul id="interets_list">
            <?php foreach ($interests as $interest): ?>
                <li id="interest_list_item-<?= htmlspecialchars($interest['key']) ?>" class="interest-element"><?= htmlspecialchars($interest['name']) ?></li>
            <?php endforeach; ?>
        </ul>
    </section>
</aside>