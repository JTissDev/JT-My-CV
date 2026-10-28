<?php
declare(strict_types=1);

/**
 * Fichier : src/helpers/request-helpers.php
 * Rôle : Contexte de la requête HTTP : valeur de la balise <base href> et démarrage de la session.
 *
 * Chargé par index.php et par registry-helpers.php (qui sert aussi le module d'impression).
 */

if (!defined('APP_STARTED') && !defined('PRINT_WANTED')) {
    exit('Direct access not permitted');
}

/** Vrai si la requête arrive en HTTPS (variable HTTPS d'Apache, ou port 443). */
function isHttpsRequest(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

/**
 * Dossier du site dans l'URL, sans slash final : '' à la racine du domaine (production),
 * '/JT-Developpeur' sous Laragon. Déduit de SCRIPT_NAME, que fixe le serveur : le visiteur
 * n'a aucune prise dessus.
 */
function appBaseFolder(): string
{
    $scriptDir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php')));

    return ($scriptDir === '/' || $scriptDir === '.') ? '' : rtrim($scriptDir, '/');
}

/**
 * Valeur de la balise <base href> : le chemin du site, avec slash final ('/' ou '/JT-Developpeur/').
 *
 * Volontairement sans nom d'hôte ni schéma : le navigateur reprend ceux de la page, ce qui
 *  - supprime toute dépendance à l'en-tête Host (qu'un visiteur ou un proxy peut falsifier) ;
 *  - ne peut jamais produire de contenu mixte (page en https, base en http) ;
 *  - fonctionne sur n'importe quel nom d'accès légitime (localhost, adresse IP du réseau local, domaine de test).
 */
function appBaseHref(): string
{
    return appBaseFolder() . '/';
}

/**
 * Démarre la session PHP, une seule fois, avec un cookie durci : HttpOnly, SameSite=Lax, Secure
 * en HTTPS, limité au dossier du site, supprimé à la fermeture du navigateur.
 *
 * À appeler uniquement par la page qui en a besoin (contact) : les autres pages n'ouvrent
 * pas de session et n'envoient plus aucun cookie.
 */
function startAppSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => appBaseHref(),
        'secure'   => isHttpsRequest(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    if (!session_start()) {
        error_log('[session] démarrage impossible');
    }
}
