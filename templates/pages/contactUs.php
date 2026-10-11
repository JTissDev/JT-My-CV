<main id="contact-page" class="pages">
   
    <h1 id="contact-title" class="page-title"><?= htmlspecialchars($contactUsData->page_title ?? 'Contactez-moi') ?></h1>
    <form action="traitement-contact.php" method="POST" class="contact-form">

        <!-- Fieldset 1 : Informations personnelles -->
        <fieldset id="personal_info" class="form-group">
            <legend><?= htmlspecialchars($contactUsData->fieldsets->personal_info ?? 'def :Vos coordonnées') ?></legend>
            
            <div class="form-field">
                <label for="name"><?= htmlspecialchars($contactUsData->fields->name->label ?? 'def :Nom / Prénom') ?> <span class="required">*</span></label>
                <input type="text" id="name" name="name" required placeholder="<?= htmlspecialchars($contactUsData->fields->name->placeholder ?? 'Votre nom complet') ?>" ?>
            </div>

            <div class="form-field">
                <label for="email"><?= htmlspecialchars($contactUsData->fields->email->label ?? 'def :Adresse e-mail') ?> <span class="required">*</span></label>
                <input type="email" id="email" name="email" required placeholder="<?= htmlspecialchars($contactUsData->fields->email->placeholder ?? 'Votre adresse email') ?>" ?>
            </div>

            <div class="form-field">
                <label for="phone"><?= htmlspecialchars($contactUsData->fields->phone->label ?? 'def :Téléphone') ?> (optionnel)</label>
                <input type="tel" id="phone" name="phone" placeholder="<?= htmlspecialchars($contactUsData->fields->phone->placeholder ?? 'Votre numéro de téléphone') ?>" ?>
            </div>
        </fieldset>

        <!-- Fieldset 2 : Motif de contact (Select) -->
        <fieldset class="form-group">
            <legend><?= htmlspecialchars($contactUsData->fieldsets->contact_reason ?? 'def :Motif de votre démarche') ?></legend>

            <div class="form-field">
                <label for="contact_reason"><?= htmlspecialchars($contactUsData->fields->object->label ?? 'def :Objet du message') ?> <span class="required">*</span></label>
                <select id="contact_reason" name="contact_reason" required>
                    <option value="" disabled selected>-- <?= htmlspecialchars($contactUsData->fields->object->select_placeholder ?? 'Choisissez un motif') ?> --</option>
                    <?php foreach ($selectOptions as $option): ?>
                        <option value="<?= htmlspecialchars($option['key']) ?>"><?= htmlspecialchars($option['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </fieldset>

        <!-- Fieldset 3 : Message -->
        <fieldset class="form-group">
            <legend><?= htmlspecialchars($contactUsData->fieldsets->message ?? 'Votre message') ?></legend>

            <div class="form-field">
                <label for="message"><?= htmlspecialchars($contactUsData->fields->message->label ?? 'Détails de votre demande') ?> <span class="required">*</span></label>
                <textarea id="message" name="message" rows="6" required placeholder="<?= htmlspecialchars($contactUsData->fields->message->placeholder ?? 'Décrivez votre projet, votre suggestion ou votre proposition...') ?>"></textarea>
            </div>
        </fieldset>

        <!-- Bouton d'envoi -->
        <div class="form-actions">
            <button type="submit" class="btn-submit">Envoyer le message</button>
        </div>

    </form>
</main>