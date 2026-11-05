<?php
declare(strict_types=1);

/**
 * Fichier : classes/dashboard/DashboardDatabase.php
 * Rôle : Connexion PDO à la base du dashboard (MariaDB / MySQL).
 *
 * Contraintes de l'hébergement : 18 connexions simultanées au plus pour l'utilisateur de la base.
 *   - une connexion COURTE par requête HTTP (jamais de connexion persistante), refermée à la fin du script ;
 *   - délai de connexion de 2 secondes ;
 *   - connectQuietly() ne lève jamais d'exception : en cas d'échec la page s'affiche normalement et la collecte
 *     est suspendue 60 secondes (disjoncteur), pour ne pas imposer 2 secondes d'attente à chaque page ni
 *     marteler une base saturée.
 *
 * Aucune adresse IP n'est écrite dans les journaux : seul le code d'erreur de la base est journalisé.
 * Compatible PHP 8.0.
 */
final class DashboardDatabase
{
    public const CONNECT_TIMEOUT_SECONDS = 2;
    public const COOLDOWN_SECONDS = 60;

    /** Mode strict : une valeur invalide est une erreur, jamais une troncature silencieuse */
    private const SQL_MODE = 'STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION';

    private function __construct()
    {
    }

    /**
     * Connexion stricte (migrations, tests, espace admin) : lève PDOException en cas d'échec.
     *
     * @param array{db_host:string,db_port:int,db_name:string,db_user:string,db_password:string} $config issue de DashboardConfig
     * @param bool $withLimits true : délai maximal de 3 s par requête et par attente de verrou (pages du site) ;
     *                         false : aucune limite (migrations, qui peuvent légitimement durer plus longtemps)
     * @throws PDOException
     */
    public static function connect(array $config, bool $withLimits = true): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $config['db_host'],
            $config['db_port'],
            $config['db_name']
        );

        $pdo = new PDO($dsn, $config['db_user'], $config['db_password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => false,
            PDO::ATTR_TIMEOUT            => self::CONNECT_TIMEOUT_SECONDS,
        ]);

        // Réglages de session communs à MariaDB et MySQL. Ils sont exécutés ici et non par une option PDO :
        // les constantes PDO::MYSQL_ATTR_* sont dépréciées depuis PHP 8.5.
        $pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
        $pdo->exec("SET time_zone = '+00:00', sql_mode = '" . self::SQL_MODE . "'");

        // Garde-fous facultatifs : une variable inconnue du moteur (MariaDB ou MySQL) est simplement ignorée.
        // Une requête qui s'éternise garderait l'une des 18 connexions : mieux vaut l'interrompre.
        if ($withLimits) {
            self::tryExec($pdo, 'SET SESSION innodb_lock_wait_timeout = 3');
            self::tryExec($pdo, 'SET SESSION max_statement_time = 3');
        }

        return $pdo;
    }

    /**
     * Connexion pour les pages du site : ne lève JAMAIS d'exception.
     * Retourne null si la base est indisponible, saturée, mal configurée, ou en pause (disjoncteur) :
     * l'appelant ignore alors simplement la collecte.
     *
     * @param array{db_host:string,db_port:int,db_name:string,db_user:string,db_password:string} $config
     * @param string|null $cooldownFile fichier du disjoncteur (par défaut : dossier temporaire, voir cooldownFile())
     */
    public static function connectQuietly(array $config, ?string $cooldownFile = null): ?PDO
    {
        $file = $cooldownFile ?? self::cooldownFile($config);

        if (self::isCoolingDown($file)) {
            return null;
        }

        try {
            return self::connect($config);
        } catch (Throwable $e) {
            self::startCooldown($file);
            error_log(
                'dashboard : base de données indisponible (code ' . self::errorCode($e) . '), collecte suspendue '
                . self::COOLDOWN_SECONDS . ' s'
            );

            return null;
        }
    }

    /** Fichier du disjoncteur : propre à cette base et à cet utilisateur (plusieurs sites peuvent partager le dossier temporaire). */
    public static function cooldownFile(array $config): string
    {
        $id = substr(hash('sha256', $config['db_host'] . '|' . $config['db_port'] . '|' . $config['db_name'] . '|' . $config['db_user']), 0, 16);

        return rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'jt-dashboard-down-' . $id;
    }

    /** Vrai si le disjoncteur est enclenché : fichier modifié il y a moins de 60 s (une date dans le futur ne compte pas). */
    public static function isCoolingDown(string $file, ?int $now = null): bool
    {
        clearstatcache(true, $file);
        $modified = @filemtime($file);
        if ($modified === false) {
            return false;
        }

        $age = ($now ?? time()) - $modified;

        return $age >= 0 && $age < self::COOLDOWN_SECONDS;
    }

    /** Enclenche le disjoncteur. Échec silencieux (dossier non inscriptible) : la collecte retentera simplement plus tôt. */
    public static function startCooldown(string $file): void
    {
        // On ne suit jamais un lien symbolique (dossier temporaire partagé)
        if (is_link($file)) {
            return;
        }

        if (@touch($file)) {
            @chmod($file, 0600);
        }
    }

    /** Code d'erreur du moteur (ex. 1226 : trop de connexions pour cet utilisateur), sinon code PDO. Jamais le message. */
    private static function errorCode(Throwable $e): string
    {
        if ($e instanceof PDOException && isset($e->errorInfo[1])) {
            return (string) (int) $e->errorInfo[1];
        }

        $code = $e->getCode();

        return is_int($code) || (is_string($code) && preg_match('/^[A-Za-z0-9]{1,10}$/', $code) === 1) ? (string) $code : '?';
    }

    /** Exécute un réglage facultatif : une erreur est ignorée (variable inconnue de ce moteur). */
    private static function tryExec(PDO $pdo, string $sql): void
    {
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            // Variable propre à l'autre moteur : sans importance
        }
    }
}
