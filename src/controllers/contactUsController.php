<?php
if (!defined('APP_STARTED')) {
    exit('Direct access not permitted');
}

use PHPMailer\PHPMailer\PHPMailer;

/**
 * Fichier : src/controllers/contactUsController.php
 * Rôle : Contrôleur de la page « Contactez-moi ».
 *
 *  - GET  : affiche le formulaire (et le message de succès après la redirection) ;
 *  - POST : honeypot, jeton CSRF, validation, envoi, puis redirection 303 (Post/Redirect/Get).
 *
 * Variables fournies à la vue : $contactUsData, $selectOptions, $errors, $successMessage,
 * $old (saisies à repeupler), $csrfToken, $formAction.
 *
 * Session : cette page est la seule à en ouvrir une (jeton CSRF et message de confirmation).
 */

require_once __DIR__ . '/../helpers/contact-form.php';

startAppSession();

$contactUsData     = (isset($data) && isset($data->pages->contactUs)) ? $data->pages->contactUs : null;
$contactUsregistre = (isset($registre) && isset($registre->contactUs)) ? $registre->contactUs : null;
$formsData         = (isset($registre) && isset($registre->formsdata)) ? $registre->formsdata : null;
$errorsMessage     = (isset($data) && isset($data->meta->forms_errors)) ? $data->meta->forms_errors : null;

$formAction     = getCleanUrl('contactUs', $lang);
$csrfToken      = contactCsrfToken();
$errors         = [];
$successMessage = null;
$old            = ['name' => '', 'email' => '', 'phone' => '', 'contact_reason' => '', 'message' => ''];

// Liste blanche des motifs : les clés de data/registre.json (contactUs.select)
$allowedReasons = array_map('strval', array_keys((array) ($contactUsregistre->select ?? [])));

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {

    // Après un succès : redirection 303, pour qu'un rafraîchissement (F5) ne renvoie pas le message.
    $redirectAfterSent = static function () use ($formAction): void {
        $_SESSION[CONTACT_FLASH_SESSION_KEY] = 'sent';
        unset($_SESSION[CONTACT_CSRF_SESSION_KEY]);
        header('Location: ' . $formAction, true, 303);
        exit;
    };

    // 1. Honeypot : un robot remplit tous les champs. On simule un succès, sans rien envoyer.
    if (contactHoneypotTriggered($_POST)) {
        error_log('[contact] champ piège rempli : message ignoré');
        $redirectAfterSent();
    }

    // 2. Saisies nettoyées, conservées pour repeupler le formulaire si une erreur survient
    $old['name']           = contactSingleLine($_POST['name'] ?? null);
    $old['email']          = contactSingleLine($_POST['email'] ?? null);
    $old['phone']          = contactSingleLine($_POST['phone'] ?? null);
    $old['contact_reason'] = contactSingleLine($_POST['contact_reason'] ?? null);
    $old['message']        = contactMultiLine($_POST['message'] ?? null);

    // 3. Jeton CSRF
    if (!contactCsrfIsValid($_POST['csrf_token'] ?? null)) {
        $errors[] = $errorsMessage->csrf_error ?? 'Votre session a expiré. Veuillez renvoyer le formulaire.';
    } else {

        // 4. Validation des champs
        $email = filter_var($old['email'], FILTER_VALIDATE_EMAIL);

        if ($old['name'] === '') {
            $errors[] = $errorsMessage->name_error ?? 'Veuillez renseigner votre nom.';
        }
        if ($email === false) {
            $errors[] = $errorsMessage->mail_error ?? 'Veuillez fournir une adresse e-mail valide.';
        }
        if (!in_array($old['contact_reason'], $allowedReasons, true)) {
            $errors[] = $errorsMessage->reason_error ?? 'Veuillez sélectionner un motif de contact.';
        }
        if ($old['message'] === '') {
            $errors[] = $errorsMessage->message_error ?? 'Veuillez rédiger un message.';
        }
        if (
            contactLength($old['name']) > CONTACT_MAX_NAME
            || contactLength($old['email']) > CONTACT_MAX_EMAIL
            || contactLength($old['phone']) > CONTACT_MAX_PHONE
            || contactLength($old['message']) > CONTACT_MAX_MESSAGE
        ) {
            $errors[] = $errorsMessage->length_error ?? 'Un des champs dépasse la longueur autorisée.';
        }

        // 5. Envoi de l'e-mail
        if (empty($errors)) {
            $sent = false;

            try {
                $mail = new PHPMailer(true);

                $to   = $formsData->mail ?? 'jtissdev@gmail.com';
                $from = $formsData->from ?? 'no-reply@jtissier-developpeur-informatique.fr';

                $mail->setFrom($from, 'CV Web - Contact');
                $mail->addAddress($to, 'Julien Tissier');
                $mail->addReplyTo($email, $old['name']); // Permet de répondre directement à la personne

                // Textes depuis le registre (le motif est une clé de la liste blanche)
                $reasonLabel   = $contactUsregistre->select->{$old['contact_reason']}->object ?? $old['contact_reason'];
                $subjectPrefix = $formsData->subject ?? 'CV Web - Nouveau message : ';
                $mail->Subject = $subjectPrefix . ' ' . $reasonLabel;

                $bodyConfig       = $formsData->body ?? null;
                $phonePlaceholder = $formsData->phone_placeholder ?? 'Non Renseigné';

                $body  = $bodyConfig->message ?? "Vous avez reçu un nouveau message depuis votre site CV Web :\n\n";
                $body .= ($bodyConfig->name ?? 'Nom : ') . $old['name'] . "\n";
                $body .= ($bodyConfig->email ?? 'Email : ') . $email . "\n";
                $body .= ($bodyConfig->phone ?? 'Téléphone : ') . ($old['phone'] !== '' ? $old['phone'] : $phonePlaceholder) . "\n";
                $body .= ($bodyConfig->reason ?? 'Motif : ') . $reasonLabel . "\n\n";
                $body .= ($bodyConfig->message_content ?? "Message :\n") . $old['message'] . "\n";

                $mail->Body    = $body;
                $mail->CharSet = 'UTF-8';

                $mail->send();
                $sent = true;
            } catch (Throwable $e) {
                // Le détail technique va dans le journal du serveur, jamais à l'écran du visiteur
                error_log('[contact] envoi impossible : ' . $e->getMessage() . (isset($mail) ? ' | ' . $mail->ErrorInfo : ''));
                $errors[] = $contactUsData->error_message ?? "Une erreur est survenue lors de l'envoi du message. Veuillez réessayer plus tard.";
            }

            if ($sent) {
                $redirectAfterSent();
            }
        }
    }
} elseif (($_SESSION[CONTACT_FLASH_SESSION_KEY] ?? null) === 'sent') {
    // Retour après une redirection réussie : message affiché une seule fois
    unset($_SESSION[CONTACT_FLASH_SESSION_KEY]);
    $successMessage = $contactUsData->success_message ?? 'Merci pour votre message ! Je vous répondrai dans les plus brefs délais.';
}

$selectOptions = [];
if (isset($contactUsregistre->select)) {

    foreach ($contactUsregistre->select as $key => $value) {

        $selectOptions[$key] = [
            'key'    => $key,
            'object' => $contactUsregistre->select->$key->object,
            'label'  => $contactUsData->fields->object->options->$key ?? $key
        ];
    }
}
