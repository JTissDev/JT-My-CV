<?php
declare(strict_types=1);

/**
 * Fichier : classes/dashboard/CollectorHook.php
 * Rôle : Point d'entrée de la collecte d'audience. index.php l'appelle UNE fois, juste après la résolution de la route.
 *
 *     require_once __DIR__ . '/classes/dashboard/CollectorHook.php';
 *     CollectorHook::register();
 *
 * La page est d'abord entièrement envoyée au visiteur ; la collecte s'exécute ensuite, à la fin du script (fonction de
 * fin enregistrée par register_shutdown_function). On lit alors le code HTTP réel : 200, 301, 404, ou 500 (y compris
 * une erreur PHP fatale). La collecte ne peut donc ni ralentir une page ni la casser :
 *   - pas de fichier dashboard.config.php : rien ne se passe (c'est l'interrupteur général) ;
 *   - base absente, saturée ou en erreur : la page est déjà partie, l'erreur est journalisée (sans donnée du visiteur),
 *     et la collecte est suspendue 60 secondes (disjoncteur de DashboardDatabase) ;
 *   - rafale de 404 (scanner) : au-delà de 30 par minute, ou de 600 écritures par minute toutes pages confondues,
 *     les demandes sont ignorées SANS ouvrir de connexion, puis résumées en une ligne d'erreur par jour ;
 *   - toute exception est attrapée ici.
 *
 * Le détail des règles (ce qui est enregistré, DNT, visites) est dans VisitCollector et docs/dashboard-collecte.md.
 * Les classes de la collecte ne sont chargées qu'à la fin de la page, dans la zone protégée : un fichier manquant
 * ne casse jamais l'affichage. Compatible PHP 8.0.
 */
final class CollectorHook
{
    /** Plafond de 404 détaillées : 30 par fenêtre de 60 secondes. */
    public const LIMIT_NOT_FOUND = 30;

    /** Plafond d'écritures, toutes pages confondues : 600 par fenêtre de 60 secondes. */
    public const LIMIT_WRITES = 600;

    public const LIMIT_WINDOW_SECONDS = 60;

    /** Erreurs PHP qui terminent le script : la page est alors envoyée avec le code 500. */
    private const FATAL_ERRORS = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR;

    private static bool $registered = false;

    private function __construct()
    {
    }

    /**
     * Branche la collecte sur la fin du script. Sans effet en ligne de commande (tests, scripts) et si déjà branchée.
     * Ne lève jamais d'exception.
     */
    public static function register(): void
    {
        if (self::$registered || PHP_SAPI === 'cli') {
            return;
        }

        self::$registered = true;
        register_shutdown_function([self::class, 'shutdown']);
    }

    /** Fin du script : envoie la page au visiteur, puis collecte. Ne lève jamais d'exception. */
    public static function shutdown(): void
    {
        try {
            // À lire avant tout autre appel : le code HTTP envoyé et la dernière erreur PHP
            $httpCode  = http_response_code();
            $lastError = error_get_last();

            // Le visiteur reçoit sa page maintenant ; la suite se passe sans qu'il attende
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }

            $page = $GLOBALS['page'] ?? null;
            $lang = $GLOBALS['lang'] ?? 'fr';

            self::handle([
                'server'     => $_SERVER,
                'query'      => $_GET,
                'page'       => is_string($page) ? $page : null,
                'lang'       => is_string($lang) ? $lang : 'fr',
                'http_code'  => $httpCode,
                'last_error' => $lastError,
            ]);
        } catch (Throwable $e) {
            error_log('dashboard : collecte abandonnée (' . self::describe($e) . ')');
        }
    }

    /**
     * Code HTTP à enregistrer : une erreur PHP fatale vaut 500 (le serveur l'enverra, même si http_response_code() dit
     * encore 200 à ce moment) ; sinon le code de la réponse (200 quand aucun n'a été positionné).
     *
     * @param mixed $httpCode retour de http_response_code() : un entier, ou false hors serveur web
     * @param array{type?:int}|null $lastError retour de error_get_last()
     */
    public static function resolveStatus($httpCode, ?array $lastError): int
    {
        if ($lastError !== null && (((int) ($lastError['type'] ?? 0)) & self::FATAL_ERRORS) !== 0) {
            return 500;
        }

        return is_int($httpCode) ? $httpCode : 200;
    }

    /**
     * Traite une requête terminée. Appelée par shutdown() ; les tests l'appellent directement.
     *
     * @param array{server:array<string,mixed>,query:array<string,mixed>,page:?string,lang:string,http_code:mixed,last_error:?array} $context
     * @param array{config?:array,connect?:callable,limiter?:BurstLimiter,now?:int,cooldown_file?:string} $overrides
     *        remplacements pour les tests : configuration, connexion, limiteur, instant, fichier du disjoncteur
     * @return string skipped (requête non collectée) · disabled (pas de configuration) · misconfigured · limited (frein) ·
     *                unavailable (base injoignable) · failed (écriture impossible) · ou le résultat de VisitCollector::record()
     */
    public static function handle(array $context, array $overrides = []): string
    {
        if (!self::loadClasses()) {
            return 'failed';
        }

        $status  = self::resolveStatus($context['http_code'], $context['last_error']);
        $request = VisitCollector::buildRequest($context['server'], $context['query'], $context['page'], $context['lang'], $status);
        if (!VisitCollector::wanted($request)) {
            return 'skipped';
        }

        // Interrupteur général : pas de fichier de configuration, pas de collecte
        if (isset($overrides['config'])) {
            $config = $overrides['config'];
        } else {
            if (DashboardConfig::locate() === null) {
                return 'disabled';
            }
            try {
                $config = DashboardConfig::load();
            } catch (RuntimeException $e) {
                // Les messages de DashboardConfig ne citent jamais le mot de passe ni le secret
                error_log('dashboard : configuration inutilisable, collecte arrêtée (' . $e->getMessage() . ')');

                return 'misconfigured';
            }
        }

        // Freins : avant toute connexion à la base
        $now     = $overrides['now'] ?? time();
        $limiter = $overrides['limiter'] ?? new BurstLimiter();
        if ($status === 404 && !$limiter->allow('notfound', self::LIMIT_NOT_FOUND, self::LIMIT_WINDOW_SECONDS, $now)) {
            return 'limited';
        }
        if (!$limiter->allow('writes', self::LIMIT_WRITES, self::LIMIT_WINDOW_SECONDS, $now)) {
            return 'limited';
        }

        $cooldownFile = $overrides['cooldown_file'] ?? DashboardDatabase::cooldownFile($config);
        $pdo = isset($overrides['connect'])
            ? $overrides['connect']($config)
            : DashboardDatabase::connectQuietly($config, $cooldownFile);
        if (!$pdo instanceof PDO) {
            return 'unavailable';
        }

        try {
            $outcome = VisitCollector::record($pdo, $config['server_secret'], $request, $now);
            self::flushDropped($pdo, $limiter, $now);

            return $outcome;
        } catch (Throwable $e) {
            error_log('dashboard : écriture impossible (' . self::describe($e) . ')');

            // Un problème durable (table absente, droits, base lente) ne doit pas se répéter à chaque page :
            // seul un interblocage passager ne suspend rien
            if (!VisitCollector::isDeadlock($e)) {
                DashboardDatabase::startCooldown($cooldownFile);
            }

            return 'failed';
        }
    }

    /**
     * Charge les classes de la collecte. Un fichier absent (déploiement incomplet) est un échec journalisé, pas une
     * erreur fatale : require_once, lui, ne peut pas être attrapé.
     */
    private static function loadClasses(): bool
    {
        foreach (['VisitCollector', 'BurstLimiter', 'DashboardConfig', 'DashboardDatabase'] as $class) {
            $file = __DIR__ . DIRECTORY_SEPARATOR . $class . '.php';
            if (!is_file($file)) {
                error_log('dashboard : fichier manquant, collecte arrêtée (' . $class . '.php)');

                return false;
            }
            require_once $file;
        }

        return true;
    }

    /** Écrit dans errors le nombre de demandes que les freins ont ignorées depuis la dernière écriture réussie. */
    private static function flushDropped(PDO $pdo, BurstLimiter $limiter, int $now): void
    {
        $notFound = $limiter->takeDropped('notfound');
        if ($notFound > 0) {
            VisitCollector::recordDropped($pdo, 'not_found', VisitCollector::SUBJECT_NOT_FOUND_BURST, $notFound, $now);
        }

        $writes = $limiter->takeDropped('writes');
        if ($writes > 0) {
            VisitCollector::recordDropped($pdo, 'security', VisitCollector::SUBJECT_WRITE_CEILING, $writes, $now);
        }
    }

    /** Description d'une erreur sans donnée du visiteur (VisitCollector::describe, mais sans exiger son chargement avant). */
    private static function describe(Throwable $e): string
    {
        if (class_exists('VisitCollector', false)) {
            return VisitCollector::describe($e);
        }

        return get_class($e);
    }
}
