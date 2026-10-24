<?php
declare(strict_types=1);

/**
 * Fichier : src/helpers/contact-form.php
 * Rôle : Constantes et fonctions pures du formulaire de contact
 *        (nettoyage des saisies, limites de longueur, jeton CSRF, échappement HTML).
 *
 * Chargé par src/controllers/contactUsController.php.
 */

if (!defined('APP_STARTED')) {
    exit('Direct access not permitted');
}

/** Longueurs maximales en caractères : utilisées par le serveur ET par l'attribut maxlength de la vue. */
const CONTACT_MAX_NAME    = 100;
const CONTACT_MAX_EMAIL   = 254;
const CONTACT_MAX_PHONE   = 30;
const CONTACT_MAX_MESSAGE = 5000;

/** Clés de session. */
const CONTACT_CSRF_SESSION_KEY  = 'contact_csrf';
const CONTACT_FLASH_SESSION_KEY = 'contact_flash';

/** Nom du champ piège (honeypot) : invisible pour un humain, rempli par la plupart des robots. */
const CONTACT_HONEYPOT_FIELD = 'hp_field';

/**
 * Saisie sur une seule ligne (nom, e-mail, téléphone, motif).
 *
 * Une valeur qui n'est pas une chaîne (`name[]=x`, absente) ou qui n'est pas de l'UTF-8 valide
 * donne ''. Les caractères de contrôle, dont CR et LF, et les espaces répétées deviennent une
 * seule espace ; les bords sont retirés.
 */
function contactSingleLine(mixed $value): string
{
    if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
        return '';
    }

    $clean = preg_replace('/[\p{Cc}\p{Zl}\p{Zp}\s]+/u', ' ', $value);

    return trim($clean ?? '');
}

/**
 * Saisie sur plusieurs lignes (message).
 *
 * Mêmes règles que contactSingleLine, mais les retours à la ligne (normalisés en LF) et les
 * tabulations sont conservés ; les autres caractères de contrôle sont supprimés.
 */
function contactMultiLine(mixed $value): string
{
    if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
        return '';
    }

    $text  = str_replace(["\r\n", "\r"], "\n", $value);
    $text  = preg_replace('/[\p{Zl}\p{Zp}]/u', "\n", $text) ?? '';
    $clean = preg_replace('/(?![\n\t])\p{Cc}/u', '', $text);

    return trim($clean ?? '');
}

/** Longueur en caractères (et non en octets). */
function contactLength(string $value): int
{
    return mb_strlen($value, 'UTF-8');
}

/** Échappement HTML d'une saisie utilisateur (guillemets simples et doubles inclus, UTF-8 invalide remplacé). */
function contactEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Jeton CSRF de la session : créé au premier appel, identique ensuite jusqu'à l'envoi réussi.
 * Nécessite une session démarrée.
 */
function contactCsrfToken(): string
{
    $token = $_SESSION[CONTACT_CSRF_SESSION_KEY] ?? null;

    if (!is_string($token) || $token === '') {
        $token = bin2hex(random_bytes(32));
        $_SESSION[CONTACT_CSRF_SESSION_KEY] = $token;
    }

    return $token;
}

/** Compare le jeton reçu à celui de la session, en temps constant. */
function contactCsrfIsValid(mixed $submitted): bool
{
    $expected = $_SESSION[CONTACT_CSRF_SESSION_KEY] ?? null;

    return is_string($submitted)
        && is_string($expected)
        && $expected !== ''
        && hash_equals($expected, $submitted);
}

/** Vrai si le champ piège contient quoi que ce soit (y compris "0" ou un tableau). */
function contactHoneypotTriggered(array $post): bool
{
    return ($post[CONTACT_HONEYPOT_FIELD] ?? '') !== '';
}
