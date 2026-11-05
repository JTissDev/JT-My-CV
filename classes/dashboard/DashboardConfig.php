<?php
declare(strict_types=1);

/**
 * Fichier : classes/dashboard/DashboardConfig.php
 * Rôle : Localise, lit et valide la configuration du dashboard (identifiants de la base et secret serveur).
 *
 * La configuration vit dans UN fichier PHP, jamais dans Git ni dans la base de données :
 *   - en local : dashboard.config.php à la racine du projet (listé dans .gitignore) ;
 *   - en production : le même fichier, déposé à la main à la racine du site (conservé par le déploiement) ;
 *   - au choix : le chemin peut être imposé par la variable d'environnement JT_DASHBOARD_CONFIG.
 * Modèle : docs/dashboard.config.example.php
 *
 * Les messages d'erreur ne contiennent jamais le mot de passe ni le secret.
 * Compatible PHP 8.0.
 */
final class DashboardConfig
{
    public const FILE_NAME = 'dashboard.config.php';
    public const ENV_VARIABLE = 'JT_DASHBOARD_CONFIG';

    /** Clés acceptées dans le fichier (toute autre clé est refusée : elle trahit presque toujours une faute de frappe) */
    private const KEYS = ['db_host', 'db_port', 'db_name', 'db_user', 'db_password', 'secret'];

    private function __construct()
    {
    }

    /** Chemin habituel du fichier : racine du projet (classes/dashboard/ -> deux niveaux au-dessus). */
    public static function defaultPath(): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . self::FILE_NAME;
    }

    /**
     * Fichier à utiliser : le chemin imposé, sinon la variable d'environnement, sinon le chemin habituel.
     * Retourne null si rien n'existe.
     */
    public static function locate(?string $override = null): ?string
    {
        $candidates = [];
        if ($override !== null && $override !== '') {
            $candidates[] = $override;
        }

        $fromEnv = getenv(self::ENV_VARIABLE);
        if (is_string($fromEnv) && $fromEnv !== '') {
            $candidates[] = $fromEnv;
        }

        $candidates[] = self::defaultPath();

        foreach ($candidates as $path) {
            if (is_file($path) && is_readable($path)) {
                return $path;
            }

            // Un chemin imposé explicitement qui n'existe pas est une erreur, pas une raison d'essayer le suivant
            if ($path === $override || $path === $fromEnv) {
                return null;
            }
        }

        return null;
    }

    /**
     * Lit et valide la configuration.
     *
     * @return array{db_host:string,db_port:int,db_name:string,db_user:string,db_password:string,server_secret:string}
     *         server_secret : 32 octets (ou plus) en binaire
     * @throws RuntimeException fichier introuvable, illisible ou invalide
     */
    public static function load(?string $override = null): array
    {
        $path = self::locate($override);
        if ($path === null) {
            throw new RuntimeException(
                'Fichier de configuration du dashboard introuvable (' . self::FILE_NAME . ' à la racine du site, '
                . 'ou variable ' . self::ENV_VARIABLE . ').'
            );
        }

        try {
            $raw = (static function (string $file) {
                return include $file;
            })($path);
        } catch (Throwable $e) {
            // Le message d'une erreur de syntaxe peut citer une ligne du fichier : on ne le recopie pas
            throw new RuntimeException('Le fichier de configuration du dashboard est illisible (erreur PHP : ' . get_class($e) . ').');
        }

        if (!is_array($raw)) {
            throw new RuntimeException('Le fichier de configuration du dashboard doit se terminer par « return [ ... ]; ».');
        }

        return self::validate($raw);
    }

    /**
     * Valide un tableau de configuration et le normalise.
     *
     * @param array<mixed> $raw
     * @return array{db_host:string,db_port:int,db_name:string,db_user:string,db_password:string,server_secret:string}
     * @throws RuntimeException
     */
    public static function validate(array $raw): array
    {
        foreach (array_keys($raw) as $key) {
            if (!in_array($key, self::KEYS, true)) {
                throw new RuntimeException('Clé inconnue dans la configuration du dashboard : « ' . self::printable((string) $key) . ' ».');
            }
        }

        foreach (['db_host', 'db_name', 'db_user', 'db_password', 'secret'] as $required) {
            if (!array_key_exists($required, $raw)) {
                throw new RuntimeException('Clé manquante dans la configuration du dashboard : « ' . $required . ' ».');
            }
            if (!is_string($raw[$required])) {
                throw new RuntimeException('La clé « ' . $required . ' » doit être une chaîne de caractères.');
            }
        }

        $host = $raw['db_host'];
        if (strlen($host) > 253 || preg_match('/^[A-Za-z0-9]([A-Za-z0-9._-]*[A-Za-z0-9])?$/', $host) !== 1) {
            throw new RuntimeException('« db_host » est invalide (nom de serveur ou adresse IPv4 attendu).');
        }

        $port = $raw['db_port'] ?? 3306;
        if (!is_int($port) || $port < 1 || $port > 65535) {
            throw new RuntimeException('« db_port » doit être un entier entre 1 et 65535.');
        }

        $name = $raw['db_name'];
        if (preg_match('/^[A-Za-z0-9_]{1,64}$/', $name) !== 1) {
            throw new RuntimeException('« db_name » est invalide (1 à 64 lettres, chiffres ou « _ »).');
        }

        $user = $raw['db_user'];
        if ($user === '' || strlen($user) > 80 || preg_match('/[\x00-\x1F\x7F]/', $user) === 1) {
            throw new RuntimeException('« db_user » est invalide (1 à 80 caractères, sans caractère de contrôle).');
        }

        $password = $raw['db_password'];
        if (strlen($password) > 256 || preg_match('/[\x00-\x08\x0A-\x1F\x7F]/', $password) === 1) {
            throw new RuntimeException('« db_password » est invalide (256 caractères au plus, sans retour à la ligne).');
        }

        $secret = $raw['secret'];
        if (preg_match('/^[0-9a-fA-F]{64,512}$/', $secret) !== 1 || strlen($secret) % 2 !== 0) {
            throw new RuntimeException('« secret » doit contenir au moins 64 caractères hexadécimaux (32 octets) : voir docs/dashboard.config.example.php.');
        }
        if (count(array_unique(str_split(strtolower($secret)))) < 8) {
            throw new RuntimeException('« secret » est trop régulier pour être aléatoire : générez-le avec « openssl rand -hex 32 ».');
        }

        $binary = hex2bin($secret);
        if ($binary === false) {
            throw new RuntimeException('« secret » est invalide.');
        }

        return [
            'db_host'       => $host,
            'db_port'       => $port,
            'db_name'       => $name,
            'db_user'       => $user,
            'db_password'   => $password,
            'server_secret' => $binary,
        ];
    }

    /** Version affichable d'un nom de clé du fichier de configuration (longueur et caractères bornés). */
    private static function printable(string $text): string
    {
        $clean = preg_replace('/[^A-Za-z0-9_.-]/', '?', substr($text, 0, 40));

        return $clean === null ? '?' : $clean;
    }
}
