<?php
/**
 * Fichier : templates/pages/contactUs.php
 * Rôle : Vue de la page « Contactez-moi ».
 *
 * Variables fournies par src/controllers/contactUsController.php :
 * $contactUsData, $selectOptions, $errors, $successMessage, $old, $csrfToken, $formAction.
 */
?>
<main id="contact-page" class="pages">

    <h1 id="contact-title" class="page-title"><?= htmlspecialchars($contactUsData->page_title ?? 'Contactez-moi') ?></h1>

    <!-- Affichage des retours utilisateurs (succès / erreurs) -->
    <?php if (!empty($successMessage)): ?>
        <div class="alert alert-success" role="status">
            <?= htmlspecialchars($successMessage) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" role="alert">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="<?= contactEscape($formAction) ?>" method="post" class="contact-form" accept-charset="UTF-8">

        <!-- Protection CSRF -->
        <input type="hidden" name="csrf_token" value="<?= contactEscape($csrfToken) ?>">

        <!-- Champ piège anti-robots : invisible pour un humain, ne pas remplir -->
        <div hidden aria-hidden="true">
            <input type="text" name="<?= CONTACT_HONEYPOT_FIELD ?>" value="" tabindex="-1" autocomplete="off">
        </div>

        <!-- Fieldset 1 : Informations personnelles -->
        <fieldset id="personal_info" class="form-group">
            <legend><?= htmlspecialchars($contactUsData->fieldsets->personal_info ?? 'def :Vos coordonnées') ?></legend>

            <div class="form-field">
                <label for="name"><?= htmlspecialchars($contactUsData->fields->name->label ?? 'def :Nom / Prénom') ?> <span class="required">*</span></label>
                <input type="text" id="name" name="name" required maxlength="<?= CONTACT_MAX_NAME ?>" autocomplete="name" value="<?= contactEscape($old['name']) ?>" placeholder="<?= htmlspecialchars($contactUsData->fields->name->placeholder ?? 'Votre nom complet') ?>">
            </div>

            <div class="form-field">
                <label for="email"><?= htmlspecialchars($contactUsData->fields->email->label ?? 'def :Adresse e-mail') ?> <span class="required">*</span></label>
                <input type="email" id="email" name="email" required maxlength="<?= CONTACT_MAX_EMAIL ?>" autocomplete="email" value="<?= contactEscape($old['email']) ?>" placeholder="<?= htmlspecialchars($contactUsData->fields->email->placeholder ?? 'Votre adresse email') ?>">
            </div>

            <div class="form-field">
                <label for="phone"><?= htmlspecialchars($contactUsData->fields->phone->label ?? 'def :Téléphone') ?> (optionnel)</label>
                <input type="tel" id="phone" name="phone" maxlength="<?= CONTACT_MAX_PHONE ?>" autocomplete="tel" value="<?= contactEscape($old['phone']) ?>" placeholder="<?= htmlspecialchars($contactUsData->fields->phone->placeholder ?? 'Votre numéro de téléphone') ?>">
            </div>
        </fieldset>

        <!-- Fieldset 2 : Motif de contact (Select) -->
        <fieldset class="form-group">
            <legend><?= htmlspecialchars($contactUsData->fieldsets->contact_reason ?? 'def :Motif de votre démarche') ?></legend>

            <div class="form-field">
                <label for="contact_reason"><?= htmlspecialchars($contactUsData->fields->object->label ?? 'def :Objet du message') ?> <span class="required">*</span></label>
                <select id="contact_reason" name="contact_reason" required>
                    <option value="" disabled<?= $old['contact_reason'] === '' ? ' selected' : '' ?>>-- <?= htmlspecialchars($contactUsData->fields->object->select_placeholder ?? 'Choisissez un motif') ?> --</option>
                    <?php foreach ($selectOptions as $option): ?>
                        <option value="<?= contactEscape((string) $option['key']) ?>"<?= (string) $option['key'] === $old['contact_reason'] ? ' selected' : '' ?>><?= htmlspecialchars($option['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </fieldset>

        <!-- Fieldset 3 : Message -->
        <fieldset class="form-group">
            <legend><?= htmlspecialchars($contactUsData->fieldsets->message ?? 'Votre message') ?></legend>

            <div class="form-field">
                <label for="message"><?= htmlspecialchars($contactUsData->fields->message->label ?? 'Détails de votre demande') ?> <span class="required">*</span></label>
                <textarea id="message" name="message" rows="6" required maxlength="<?= CONTACT_MAX_MESSAGE ?>" placeholder="<?= htmlspecialchars($contactUsData->fields->message->placeholder ?? 'Décrivez votre projet, votre suggestion ou votre proposition...') ?>"><?= contactEscape($old['message']) ?></textarea>
            </div>
        </fieldset>

        <!-- Bouton d'envoi -->
        <div class="form-actions">
            <button type="submit" class="btn-submit"><?= htmlspecialchars($contactUsData->submit_button ?? 'def : Envoyer le message') ?></button>
        </div>

    </form>
</main>