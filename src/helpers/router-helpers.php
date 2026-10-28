<?php
declare(strict_types=1);

/**
 * Fichier : src/helpers/router-helpers.php
 * Rôle : Logique du routeur (index.php) : lecture sans risque des paramètres d'URL,
 *        résolution de la page demandée, décision de redirection 301 vers l'adresse canonique.
 *
 * Ces fonctions ne produisent aucune sortie et n'envoient aucun en-tête : tout est calculé
 * à partir de ce qu'on leur passe, index.php se charge d'envoyer le résultat.
 */

if (!defined('APP_STARTED')) {
    exit('Direct access not permitted');
}

/** Langues servies par le site ; la première est la langue par défaut. */
const ROUTER_LANGS = ['fr', 'en'];

/** Forme d'un slug d'URL (en minuscules) : la même que celle de la règle de réécriture du .htaccess. */
const ROUTER_SLUG_PATTERN = '/^[a-z0-9_-]{1,64}$/';

/** Forme d'une clé de page interne : elle sert à construire des noms de contrôleur et de vue. */
const ROUTER_PAGE_KEY_PATTERN = '/^[A-Za-z0-9_-]{1,64}$/';

/**
 * Table des adresses acceptées : slug (en minuscules) => clé de page interne.
 *
 * Reprend les slugs du registre (meta.slugs), plus la clé interne de chaque page ('contactUs',
 * 'cgu-cgv'...) : ce sont les anciennes adresses du type index.php?page=contactUs, qui
 * restent valables et sont redirigées vers l'adresse canonique.
 * Une entrée mal formée est ignorée plutôt que de faire échouer tout le site.
 *
 * @param array<int|string, mixed> $slugMap meta.slugs du registre (slug => page interne)
 * @return array<string, string>
 */
function routerBuildAliases(array $slugMap): array
{
    $aliases = [];

    foreach ($slugMap as $slug => $page) {
        $slug = strtolower((string) $slug);

        if (is_string($page)
            && preg_match(ROUTER_SLUG_PATTERN, $slug) === 1
            && preg_match(ROUTER_PAGE_KEY_PATTERN, $page) === 1) {
            $aliases[$slug] = $page;
        }
    }

    foreach (array_unique(array_values($aliases)) as $page) {
        $key = strtolower($page);

        if (!isset($aliases[$key])) {
            $aliases[$key] = $page;
        }
    }

    return $aliases;
}

/**
 * Détermine la page et la langue demandées, sans jamais échouer sur un paramètre inattendu.
 *
 *  - page absente ou vide        : accueil ;
 *  - page inconnue ou en tableau : null, c'est-à-dire une page introuvable (404) ;
 *  - langue absente, inconnue ou en tableau : français.
 *
 * 'isRoot' vaut true quand ni page ni langue n'ont été transmises (adresse racine du site).
 *
 * @param array<string, mixed> $query   Paramètres d'URL ($_GET)
 * @param array<string, string> $aliases Résultat de routerBuildAliases()
 * @return array{page: string|null, lang: string, isRoot: bool}
 */
function routerResolve(array $query, array $aliases): array
{
    $pageGiven = array_key_exists('page', $query);
    $langGiven = array_key_exists('lang', $query);

    $page = 'home';

    if ($pageGiven) {
        $raw = $query['page'];

        if (!is_string($raw)) {
            $page = null;
        } else {
            $slug = strtolower(trim($raw));

            if ($slug === '') {
                $page = 'home';
            } elseif (preg_match(ROUTER_SLUG_PATTERN, $slug) === 1 && isset($aliases[$slug])) {
                $page = $aliases[$slug];
            } else {
                $page = null;
            }
        }
    }

    $lang = ROUTER_LANGS[0];

    if ($langGiven && is_string($query['lang'])) {
        $candidate = strtolower(trim($query['lang']));

        if (in_array($candidate, ROUTER_LANGS, true)) {
            $lang = $candidate;
        }
    }

    return ['page' => $page, 'lang' => $lang, 'isRoot' => !$pageGiven && !$langGiven];
}
