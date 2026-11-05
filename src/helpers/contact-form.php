<?php
declare(strict_types=1);

/**
 * Fichier : src/helpers/contact-form.php
 * Rôle : Constantes et fonctions pures du formulaire de contact
 *        (nettoyage des saisies, limites de longueur, jeton horodaté, contrôle de l'origine, échappement HTML).
 *
 * Chargé par src/controllers/contactUsController.php.
 *
 * Aucune session et aucun cookie : le site n'en dépose pas (voir la politique de confidentialité).
 * Trois protections se complètent :
 *   1. le champ piège (honeypot) ;
 *   2. un jeton signé qui porte sa date d'émission : refusé s'il est absent, falsifié, trop récent
 *      (un robot envoie en quelques millisecondes) ou trop ancien ;
 *   3. le contrôle de l'origine de la requête (Sec-Fetch-Site, sinon Origin), qui est la vraie défense CSRF :
 *      un site tiers ne peut pas faire envoyer le formulaire par le navigateur d'un visiteur.
 * Le jeton n'est pas lié à un navigateur et peut servir plusieurs fois : il ne remplace pas le contrôle
 * de l'origine, il écarte les envois qui ne sont pas passés par le formulaire.
 */

if (!defined('APP_STARTED')) {
    exit('Direct access not permitted');
}

/** Longueurs maximales en caractères : utilisées par le serveur ET par l'attribut maxlength de la vue. */
const CONTACT_MAX_NAME    = 100;
const CONTACT_MAX_EMAIL   = 254;
const CONTACT_MAX_PHONE   = 30;
const CONTACT_MAX_MESSAGE = 5000;

/** Nom du champ piège (honeypot) : invisible pour un humain, rempli par la plupart des robots. */
const CONTACT_HONEYPOT_FIELD = 'hp_field';

/** Nom du champ caché qui porte le jeton du formulaire (ce nom est aussi vérifié par le test de fumée de deploy.yml). */
const CONTACT_TOKEN_FIELD = 'csrf_token';

/** Paramètre d'adresse qui porte la confirmation d'envoi après la redirection (?sent=...). */
const CONTACT_SENT_PARAM = 'sent';

/** Âge du jeton du formulaire, en secondes : au moins 3 s (aucun humain ne remplit le formulaire plus vite), au plus 6 h. */
const CONTACT_FORM_TOKEN_MIN_AGE = 3;
const CONTACT_FORM_TOKEN_MAX_AGE = 21600;

/** La confirmation d'envoi n'est affichée que dans les 2 minutes qui suivent la redirection. */
const CONTACT_SENT_TOKEN_MAX_AGE = 120;

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
 * Clé de signature des jetons : dérivée du dossier d'installation, donc propre à chaque site et sans fichier
 * de configuration à déposer. Ce n'est pas un secret fort, et ce n'est pas nécessaire : le jeton n'est pas la
 * défense CSRF (c'est le contrôle de l'origine) mais un repère horaire que les robots ne fabriquent pas.
 * Changer d'hébergement ou de dossier invalide seulement les formulaires restés ouverts.
 */
function contactTokenKey(): string
{
    return hash('sha256', 'jt-contact-form|v1|' . __DIR__, true);
}

/** Jeton « <date d'émission>.<signature> » ; la finalité (« form », « sent ») est signée mais n'est pas écrite dans le jeton. */
function contactTokenMake(string $purpose, ?int $now = null): string
{
    $issuedAt = $now ?? time();

    return $issuedAt . '.' . hash_hmac('sha256', $purpose . '|' . $issuedAt, contactTokenKey());
}

/**
 * Âge d'un jeton en secondes, ou null si ce n'est pas un jeton authentique de cette finalité
 * (absent, pas une chaîne, mal formé, signature fausse). Un âge négatif (horloge reculée) est renvoyé tel quel.
 */
function contactTokenAge(string $purpose, mixed $token, ?int $now = null): ?int
{
    if (!is_string($token) || preg_match('/^(\d{1,12})\.([0-9a-f]{64})\z/', $token, $m) !== 1) {
        return null;
    }

    $expected = hash_hmac('sha256', $purpose . '|' . $m[1], contactTokenKey());

    if (!hash_equals($expected, $m[2])) {
        return null;
    }

    return ($now ?? time()) - (int) $m[1];
}

/** Jeton du formulaire, à placer dans le champ caché. */
function contactFormToken(?int $now = null): string
{
    return contactTokenMake('form', $now);
}

/** Vrai si le jeton reçu est authentique et âgé de CONTACT_FORM_TOKEN_MIN_AGE à CONTACT_FORM_TOKEN_MAX_AGE secondes. */
function contactFormTokenIsValid(mixed $submitted, ?int $now = null): bool
{
    $age = contactTokenAge('form', $submitted, $now);

    return $age !== null && $age >= CONTACT_FORM_TOKEN_MIN_AGE && $age <= CONTACT_FORM_TOKEN_MAX_AGE;
}

/** Valeur du paramètre « sent » ajouté à l'adresse de redirection après un envoi réussi. */
function contactSentToken(?int $now = null): string
{
    return contactTokenMake('sent', $now);
}

/** Vrai si la valeur de « sent » est authentique et date de CONTACT_SENT_TOKEN_MAX_AGE secondes au plus. */
function contactSentTokenIsValid(mixed $value, ?int $now = null): bool
{
    $age = contactTokenAge('sent', $value, $now);

    return $age !== null && $age >= 0 && $age <= CONTACT_SENT_TOKEN_MAX_AGE;
}

/**
 * Vrai si la requête vient d'une page du site lui-même (défense CSRF sans session).
 *
 *  - Sec-Fetch-Site (envoyé par les navigateurs actuels, non modifiable par une page web) : seul « same-origin » passe ;
 *  - sinon Origin (envoyé par les navigateurs sur tout envoi de formulaire) : son nom d'hôte doit être celui de la
 *    requête (en-tête Host), et les ports aussi quand les deux sont écrits. « null » et les valeurs mal formées sont refusés ;
 *  - ni l'un ni l'autre (outil en ligne de commande, très ancien navigateur) : accepté, le jeton reste exigé.
 *
 * @param array<string, mixed> $server $_SERVER
 */
function contactRequestIsSameOrigin(array $server): bool
{
    $fetchSite = $server['HTTP_SEC_FETCH_SITE'] ?? null;

    if (is_string($fetchSite) && $fetchSite !== '') {
        return strtolower($fetchSite) === 'same-origin';
    }

    if (!array_key_exists('HTTP_ORIGIN', $server)) {
        return true;
    }

    $origin = $server['HTTP_ORIGIN'];
    $host   = $server['HTTP_HOST'] ?? null;

    if (!is_string($origin) || !is_string($host) || $origin === '' || $host === '') {
        return false;
    }

    $originParts = parse_url($origin);
    $hostParts   = parse_url('//' . $host);

    if (!is_array($originParts) || !is_array($hostParts)
        || !isset($originParts['scheme'], $originParts['host'], $hostParts['host'])
        || !in_array(strtolower($originParts['scheme']), ['http', 'https'], true)) {
        return false;
    }

    if (strtolower($originParts['host']) !== strtolower($hostParts['host'])) {
        return false;
    }

    return !isset($originParts['port'], $hostParts['port']) || $originParts['port'] === $hostParts['port'];
}

/** Vrai si le champ piège contient quoi que ce soit (y compris "0" ou un tableau). */
function contactHoneypotTriggered(array $post): bool
{
    return ($post[CONTACT_HONEYPOT_FIELD] ?? '') !== '';
}
