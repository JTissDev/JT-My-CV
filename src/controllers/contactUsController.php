<?php
if (!defined('APP_STARTED')) {
    exit('Direct access not permitted');
}
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
/**
 * Fichier : src/controllers/contactUsController.php
 * Rôle : Contrôleur pour la page "Contactez-moi". Gère l'affichage du formulaire de contact et le traitement des soumissions.
 */

$contactUsData = (isset($data) && isset($data->pages->contactUs)) ? $data->pages->contactUs : null;
$contactUsregistre = (isset($registre) && isset($registre->contactUs)) ? $registre->contactUs : null;

// Initialisation des variables pour la vue
$errors = [];
$successMessage = null;

// 2. Traitement du formulaire lors de la soumission POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Récupération et nettoyage des données
    $name = trim($_POST['name'] ?? '');
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $phone = trim($_POST['phone'] ?? '');
    $reasonKey = trim($_POST['contact_reason'] ?? '');
    $message = trim($_POST['message'] ?? '');

    // Validation des champs obligatoires
    if (empty($name)) {
        $errors[] = "Veuillez renseigner votre nom.";
    }
    if (!$email) {
        $errors[] = "Veuillez fournir une adresse e-mail valide.";
    }
    if (empty($reasonKey)) {
        $errors[] = "Veuillez sélectionner un motif de contact.";
    }
    if (empty($message)) {
        $errors[] = "Veuillez rédiger un message.";
    }

    // Si aucune erreur, on procède à l'envoi de l'e-mail
    if (empty($errors)) {
        $mail = new PHPMailer(true);
        try {
            // Paramètres de l'e-mail
            $to = $contactUsregistre->formsdata->mail ?? "jtissdev@gmail.com";
            $from = $contactUsregistre->formsdata->from ?? "no-reply@jtissier-developpeur-informatique.fr";

            $mail->setFrom($from, 'CV Web - Contact');
            $mail->addAddress($to, 'Julien Tissier');
            $mail->addReplyTo($email, $name); // Permet de répondre directement à la personne

            // Récupération des textes depuis le registre
            $reasonLabel = $contactUsregistre->select->$reasonKey->object ?? $reasonKey;
            $subjectPrefix = $contactUsregistre->formsdata->subject ?? "CV Web - Nouveau message : ";
            $mail->Subject = $subjectPrefix . " " . $reasonLabel;

            $bodyConfig = $contactUsregistre->formsdata->body ?? null;
            $phonePlaceholder = $contactUsregistre->formsdata->phone_placeholder ?? "Non Renseigné";

            $body  = $bodyConfig->message ?? "Vous avez reçu un nouveau message depuis votre site CV Web :\n\n";
            $body .= ($bodyConfig->name ?? "Nom : ") . $name . "\n";
            $body .= ($bodyConfig->email ?? "Email : ") . $email . "\n";
            $body .= ($bodyConfig->phone ?? "Téléphone : ") . (!empty($phone) ? $phone : $phonePlaceholder) . "\n";
            $body .= ($bodyConfig->reason ?? "Motif : ") . $reasonLabel . "\n\n";
            $body .= ($bodyConfig->message_content ?? "Message :\n") . $message . "\n";

            $mail->Body = $body;
            $mail->CharSet = 'UTF-8';

            // Envoi
            $mail->send();
            $successMessage = $contactUsData->success_message ?? "Merci pour votre message ! Je vous répondrai dans les plus brefs délais.";
        } catch (Exception $e) {
            // Enregistrement de l'erreur précise si besoin
            $errors[] = ($contactUsData->error_message ?? "Une erreur est survenue lors de l'envoi du message.") . " (Détail technique : {$mail->ErrorInfo})";
        }
    }
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
