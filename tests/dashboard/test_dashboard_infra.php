<?php
declare(strict_types=1);

/**
 * Fichier : tests/dashboard/test_dashboard_infra.php
 * Rôle : Tests de G2 qui n'ont PAS besoin d'un serveur SQL :
 *   - lecture et validation de la configuration ;
 *   - découpage et contrôle des fichiers de migration (y compris les vrais fichiers de database/migrations/) ;
 *   - cohérence du schéma (aucune colonne d'adresse IP, longueurs d'index, clés étrangères) ;
 *   - logique du migrateur (sur SQLite en mémoire) ;
 *   - connexion « silencieuse » et disjoncteur face à une base injoignable.
 * Lancement (depuis la racine du projet) : php tests/dashboard/test_dashboard_infra.php
 * Le schéma lui-même est vérifié sur une vraie base par tests/dashboard/test_db_schema.php.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../classes/dashboard/DashboardConfig.php';
require_once __DIR__ . '/../../classes/dashboard/DashboardDatabase.php';
require_once __DIR__ . '/../../classes/dashboard/DashboardMigrator.php';

$ok = 0;
$ko = 0;
function check(string $label, bool $cond): void
{
    global $ok, $ko;
    if ($cond) {
        $ok++;
    } else {
        $ko++;
        echo "ÉCHEC : {$label}\n";
    }
}

// Une exception imprévue est un échec lisible (et non un « Fatal error » qui masquerait le reste du compte rendu)
set_exception_handler(static function (Throwable $e): void {
    global $ok, $ko;
    $ko++;
    echo 'ÉCHEC : exception imprévue (' . get_class($e) . ') : ' . $e->getMessage() . "\n";
    echo "\nRésultat : {$ok} OK, {$ko} échec(s)\n";
    exit(1);
});

/** Vrai si l'appel lève une RuntimeException dont le message contient $needle. */
function throwsRuntime(callable $call, string $needle = ''): bool
{
    try {
        $call();
    } catch (RuntimeException $e) {
        return $needle === '' || strpos($e->getMessage(), $needle) !== false;
    }

    return false;
}

/** Dossier de travail jetable. */
function makeTempDir(string $prefix): string
{
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $prefix . bin2hex(random_bytes(6));
    mkdir($dir, 0700, true);

    return $dir;
}

function removeTree(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($path) && !is_link($path)) {
            removeTree($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}

$secret = bin2hex(random_bytes(32));
$validConfig = [
    'db_host'     => 'db1234.hosting-example.io',
    'db_port'     => 3306,
    'db_name'     => 'dashboard_db',
    'db_user'     => 'dbu123',
    'db_password' => 'p@ss w0rd/é',
    'secret'      => $secret,
];

// ============================================================================
// 1. Configuration : validation
// ============================================================================
$normalized = DashboardConfig::validate($validConfig);
check('config : valide', $normalized['db_host'] === 'db1234.hosting-example.io' && $normalized['db_port'] === 3306);
check('config : secret décodé en 32 octets', strlen($normalized['server_secret']) === 32 && bin2hex($normalized['server_secret']) === $secret);
check('config : mot de passe conservé tel quel', $normalized['db_password'] === 'p@ss w0rd/é');
check('config : port 3306 par défaut', (function () use ($validConfig): bool {
    $c = $validConfig;
    unset($c['db_port']);
    return DashboardConfig::validate($c)['db_port'] === 3306;
})());
check('config : mot de passe vide accepté (Laragon)', DashboardConfig::validate(['db_password' => ''] + $validConfig)['db_password'] === '');
check('config : secret en majuscules accepté', DashboardConfig::validate(['secret' => strtoupper($secret)] + $validConfig)['server_secret'] === hex2bin($secret));
check('config : secret de 128 caractères accepté', strlen(DashboardConfig::validate(['secret' => bin2hex(random_bytes(64))] + $validConfig)['server_secret']) === 64);
check('config : localhost accepté', DashboardConfig::validate(['db_host' => 'localhost'] + $validConfig)['db_host'] === 'localhost');
check('config : IPv4 acceptée', DashboardConfig::validate(['db_host' => '127.0.0.1'] + $validConfig)['db_host'] === '127.0.0.1');

$invalid = [
    'clé inconnue'                  => $validConfig + ['db_pasword' => 'x'],
    'db_host manquant'              => array_diff_key($validConfig, ['db_host' => 1]),
    'db_name manquant'              => array_diff_key($validConfig, ['db_name' => 1]),
    'db_user manquant'              => array_diff_key($validConfig, ['db_user' => 1]),
    'db_password manquant'          => array_diff_key($validConfig, ['db_password' => 1]),
    'secret manquant'               => array_diff_key($validConfig, ['secret' => 1]),
    'db_host vide'                  => ['db_host' => ''] + $validConfig,
    'db_host avec espace'           => ['db_host' => 'a b'] + $validConfig,
    'db_host avec point-virgule'    => ['db_host' => 'a;host=evil'] + $validConfig,
    'db_host commençant par un tiret' => ['db_host' => '-a'] + $validConfig,
    'db_host trop long'             => ['db_host' => str_repeat('a', 254)] + $validConfig,
    'db_host entier'                => ['db_host' => 12] + $validConfig,
    'port 0'                        => ['db_port' => 0] + $validConfig,
    'port 65536'                    => ['db_port' => 65536] + $validConfig,
    'port en chaîne'                => ['db_port' => '3306'] + $validConfig,
    'db_name avec tiret'            => ['db_name' => 'a-b'] + $validConfig,
    'db_name avec point-virgule'    => ['db_name' => 'a;b'] + $validConfig,
    'db_name vide'                  => ['db_name' => ''] + $validConfig,
    'db_name trop long'             => ['db_name' => str_repeat('a', 65)] + $validConfig,
    'db_user vide'                  => ['db_user' => ''] + $validConfig,
    'db_user avec retour ligne'     => ['db_user' => "a\nb"] + $validConfig,
    'db_password avec retour ligne' => ['db_password' => "a\nb"] + $validConfig,
    'db_password trop long'         => ['db_password' => str_repeat('a', 257)] + $validConfig,
    'secret trop court'             => ['secret' => substr($secret, 0, 62)] + $validConfig,
    'secret non hexadécimal'        => ['secret' => str_repeat('g', 64)] + $validConfig,
    'secret de longueur impaire'    => ['secret' => $secret . 'a'] + $validConfig,
    'secret placeholder zéros'      => ['secret' => str_repeat('0', 64)] + $validConfig,
    'secret placeholder répété'     => ['secret' => str_repeat('ab', 32)] + $validConfig,
    'secret en clair'               => ['secret' => 'CHANGEZ-MOI'] + $validConfig,
];
foreach ($invalid as $label => $config) {
    check("config refusée : {$label}", throwsRuntime(static function () use ($config): void {
        DashboardConfig::validate($config);
    }));
}

// Les messages d'erreur ne divulguent ni le secret ni le mot de passe
$leak = false;
foreach ($invalid as $config) {
    try {
        DashboardConfig::validate($config);
    } catch (RuntimeException $e) {
        $message = $e->getMessage();
        if (strpos($message, 'p@ss w0rd') !== false || (isset($config['secret']) && strlen($config['secret']) > 12 && strpos($message, $config['secret']) !== false)) {
            $leak = true;
        }
    }
}
check('config : aucun message ne divulgue secret ou mot de passe', !$leak);

// ============================================================================
// 2. Configuration : recherche et lecture du fichier
// ============================================================================
$work = makeTempDir('jt-g2-');
$exportConfig = static function (array $config): string {
    return "<?php\nreturn " . var_export($config, true) . ";\n";
};

$goodFile = $work . DIRECTORY_SEPARATOR . 'good.php';
file_put_contents($goodFile, $exportConfig($validConfig));
check('locate : chemin imposé existant', DashboardConfig::locate($goodFile) === $goodFile);
check('locate : chemin imposé inexistant -> null (pas de repli silencieux)', DashboardConfig::locate($work . '/absent.php') === null);
check('defaultPath : racine du projet', DashboardConfig::defaultPath() === dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'dashboard.config.php');
check('load : fichier valide', DashboardConfig::load($goodFile)['db_name'] === 'dashboard_db');

putenv(DashboardConfig::ENV_VARIABLE . '=' . $goodFile);
check('locate : variable d\'environnement', DashboardConfig::locate() === $goodFile);
putenv(DashboardConfig::ENV_VARIABLE . '=' . $work . '/absent.php');
check('locate : variable d\'environnement inexistante -> null', DashboardConfig::locate() === null);
putenv(DashboardConfig::ENV_VARIABLE);
check('locate : sans rien, le chemin habituel (absent ici ou présent chez vous)', DashboardConfig::locate() === (is_file(DashboardConfig::defaultPath()) ? DashboardConfig::defaultPath() : null));

$badFiles = [
    'syntaxe invalide'    => "<?php\nreturn [ 'a' => ;\n",
    'ne retourne rien'    => "<?php\n\$x = 1;\n",
    'retourne une chaîne' => "<?php\nreturn 'oups';\n",
    'lève une exception'  => "<?php\nthrow new Exception('secret-interne');\n",
];
foreach ($badFiles as $label => $content) {
    $file = $work . DIRECTORY_SEPARATOR . 'bad-' . md5($label) . '.php';
    file_put_contents($file, $content);
    check("load refuse : {$label}", throwsRuntime(static function () use ($file): void {
        DashboardConfig::load($file);
    }));
}
$message = '';
try {
    DashboardConfig::load($work . DIRECTORY_SEPARATOR . 'bad-' . md5('lève une exception') . '.php');
} catch (RuntimeException $e) {
    $message = $e->getMessage();
}
check('load : le message de l\'exception interne n\'est pas recopié', strpos($message, 'secret-interne') === false);
check('load : fichier introuvable', throwsRuntime(static function () use ($work): void {
    DashboardConfig::load($work . '/absent.php');
}, 'introuvable'));

// ============================================================================
// 3. Découpage SQL
// ============================================================================
$split = static fn (string $sql): array => DashboardMigrator::splitStatements($sql);

check('sql : deux instructions', $split("SELECT 1;\nSELECT 2;") === ['SELECT 1', 'SELECT 2']);
check('sql : fins de ligne CRLF', $split("SELECT 1;\r\nSELECT 2;\r\n") === ['SELECT 1', 'SELECT 2']);
check('sql : commentaire de ligne ignoré', $split("-- un ; commentaire\nSELECT 1;") === ['SELECT 1']);
check('sql : commentaire # ignoré', $split("# note\nSELECT 1;") === ['SELECT 1']);
check('sql : commentaire de fin de fichier', $split("SELECT 1;\n-- fin") === ['SELECT 1']);
check('sql : « -- » collé n\'est pas un commentaire (5--3)', $split('SELECT 5--3;') === ['SELECT 5--3']);
check('sql : commentaire de bloc', $split("SELECT /* a ; b */ 1;") === ['SELECT   1']);
check('sql : point-virgule dans une chaîne', $split("INSERT INTO t VALUES ('a;b');") === ["INSERT INTO t VALUES ('a;b')"]);
check('sql : apostrophe doublée', $split("INSERT INTO t VALUES ('l''a;b');") === ["INSERT INTO t VALUES ('l''a;b')"]);
check('sql : apostrophe échappée', $split("INSERT INTO t VALUES ('l\\'a;b');") === ["INSERT INTO t VALUES ('l\\'a;b')"]);
check('sql : guillemets doubles', $split('INSERT INTO t VALUES ("a;b");') === ['INSERT INTO t VALUES ("a;b")']);
check('sql : identifiant entre accents graves', $split('SELECT `a;b` FROM t;') === ['SELECT `a;b` FROM t']);
check('sql : tiret de commentaire dans une chaîne', $split("SELECT '-- pas un commentaire';") === ["SELECT '-- pas un commentaire'"]);
check('sql : instructions vides ignorées', $split(";;SELECT 1;;") === ['SELECT 1']);
check('sql : fichier vide', $split('') === [] && $split("-- rien\n") === []);
check('sql : instruction sur plusieurs lignes', $split("CREATE TABLE t (\n  a INT\n);") === ["CREATE TABLE t (\n  a INT\n)"]);
check('sql : BOM refusé', throwsRuntime(static fn () => $split("\xEF\xBB\xBFSELECT 1;"), 'BOM'));
check('sql : dernière instruction sans point-virgule refusée', throwsRuntime(static fn () => $split('SELECT 1'), 'terminée'));
check('sql : chaîne non fermée refusée', throwsRuntime(static fn () => $split("SELECT 'a;"), 'non fermé'));
check('sql : commentaire de bloc non fermé refusé', throwsRuntime(static fn () => $split('SELECT 1; /* oups'), 'non fermé'));
check('sql : commentaire exécutable refusé', throwsRuntime(static fn () => $split('/*!40101 SET NAMES utf8 */;'), 'exécutable'));
check('sql : DELIMITER refusé', throwsRuntime(static fn () => $split("DELIMITER \$\$\nSELECT 1;"), 'DELIMITER'));
check('sql : delimiter en minuscules refusé', throwsRuntime(static fn () => $split("delimiter //\nSELECT 1;"), 'DELIMITER'));

check('migration : enregistre sa version', (function (): bool {
    DashboardMigrator::assertRecordsItself('001', ["CREATE TABLE a (x INT)", "INSERT INTO schema_migrations (version, description, applied_at) VALUES ('001', 'd', NOW())"]);
    return true;
})());
check('migration : version incorrecte refusée', throwsRuntime(static function (): void {
    DashboardMigrator::assertRecordsItself('002', ["INSERT INTO schema_migrations (version, description, applied_at) VALUES ('001', 'd', NOW())"]);
}));
check('migration : enregistrement absent refusé', throwsRuntime(static function (): void {
    DashboardMigrator::assertRecordsItself('001', ['CREATE TABLE a (x INT)']);
}));
check('migration : enregistrement qui n\'est pas le dernier refusé', throwsRuntime(static function (): void {
    DashboardMigrator::assertRecordsItself('001', ["INSERT INTO schema_migrations (version, description, applied_at) VALUES ('001', 'd', NOW())", 'CREATE TABLE a (x INT)']);
}));
check('migration : deux enregistrements refusés', throwsRuntime(static function (): void {
    $insert = "INSERT INTO schema_migrations (version, description, applied_at) VALUES ('001', 'd', NOW())";
    DashboardMigrator::assertRecordsItself('001', [$insert, $insert]);
}));
check('migration : fichier vide refusé', throwsRuntime(static function (): void {
    DashboardMigrator::assertRecordsItself('001', []);
}));

// ============================================================================
// 4. Vrais fichiers de migration (database/migrations/)
// ============================================================================
$realDir = dirname(__DIR__, 2) . '/database/migrations';
$real = new DashboardMigrator($realDir);
$list = $real->listMigrations();
check('réel : migrations 001 et 002', array_column($list, 'version') === ['001', '002']);

/** @var array<string, array{columns: array<string, string>, indexes: list<array{primary:bool,unique:bool,cols:list<string>}>}> $schema */
$schema = [];
$foreignKeys = [];
$allStatements = [];

foreach ($list as $migration) {
    $raw = (string) file_get_contents($migration['path']);
    try {
        $statements = DashboardMigrator::splitStatements($raw);
    } catch (RuntimeException $e) {
        check("réel {$migration['version']} : découpage ({$e->getMessage()})", false);
        continue;
    }
    $allStatements[$migration['version']] = $statements;

    $recordProblem = '';
    try {
        DashboardMigrator::assertRecordsItself($migration['version'], $statements);
    } catch (RuntimeException $e) {
        $recordProblem = $e->getMessage();
    }
    check("réel {$migration['version']} : enregistrement final de sa version {$recordProblem}", $recordProblem === '');

    check("réel {$migration['version']} : aucun point-virgule dans un commentaire", preg_match('/^\s*--.*;/m', $raw) !== 1);
    check("réel {$migration['version']} : aucun BOM, aucune tabulation", strncmp($raw, "\xEF\xBB\xBF", 3) !== 0 && strpos($raw, "\t") === false);
    check("réel {$migration['version']} : instructions en ASCII", (function () use ($statements): bool {
        foreach ($statements as $statement) {
            if (preg_match('/[^\x20-\x7E\n]/', $statement) === 1) {
                return false;
            }
        }
        return true;
    })());

    foreach ($statements as $statement) {
        if (preg_match('/^CREATE TABLE IF NOT EXISTS (\w+) \((.*)\) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci$/s', $statement, $m) === 1) {
            $table = $m[1];
            $schema[$table] = ['columns' => [], 'indexes' => []];
            foreach (explode("\n", $m[2]) as $line) {
                $line = rtrim(trim($line), ',');
                if ($line === '') {
                    continue;
                }
                if (preg_match('/^(PRIMARY KEY|UNIQUE KEY \w+|KEY \w+) \(([^)]*)\)$/', $line, $im) === 1) {
                    $schema[$table]['indexes'][] = [
                        'primary' => strpos($im[1], 'PRIMARY') === 0,
                        'unique'  => strpos($im[1], 'PRIMARY') === 0 || strpos($im[1], 'UNIQUE') === 0,
                        'cols'    => array_map('trim', explode(',', $im[2])),
                    ];
                } elseif (preg_match('/^CONSTRAINT (\w+) FOREIGN KEY \((\w+)\) REFERENCES (\w+) \((\w+)\) ON DELETE (CASCADE|SET NULL)$/', $line, $fm) === 1) {
                    $foreignKeys[] = ['table' => $table, 'column' => $fm[2], 'ref_table' => $fm[3], 'ref_column' => $fm[4], 'on_delete' => $fm[5]];
                } elseif (preg_match('/^(\w+) (.+)$/', $line, $cm) === 1) {
                    $schema[$table]['columns'][$cm[1]] = $cm[2];
                } else {
                    check("réel : ligne de table non reconnue ({$table}) : {$line}", false);
                }
            }
        } elseif (preg_match('/^ALTER TABLE (\w+) ADD CONSTRAINT (\w+) FOREIGN KEY \((\w+)\) REFERENCES (\w+) \((\w+)\) ON DELETE (CASCADE|SET NULL)$/', $statement, $am) === 1) {
            $foreignKeys[] = ['table' => $am[1], 'column' => $am[3], 'ref_table' => $am[4], 'ref_column' => $am[5], 'on_delete' => $am[6]];
        } elseif (preg_match('/^INSERT INTO schema_migrations\b/', $statement) !== 1) {
            check('réel : instruction non reconnue : ' . substr($statement, 0, 60), false);
        }
    }
}

check('réel : 001 = 7 instructions (6 tables + enregistrement)', count($allStatements['001'] ?? []) === 7);
check('réel : 002 = 4 instructions (2 tables, 1 clé étrangère, enregistrement)', count($allStatements['002'] ?? []) === 4);
$expectedTables = ['daily_stats', 'errors', 'guided_links', 'hits', 'login_attempts', 'schema_migrations', 'settings', 'visits'];
$foundTables = array_keys($schema);
sort($foundTables);
check('réel : les 8 tables attendues', $foundTables === $expectedTables);

// Vie privée : aucune colonne ne peut recevoir une adresse IP, un User-Agent complet, un Referer complet, une requête ou un e-mail
$forbiddenColumns = ['ip', 'ip_address', 'ipaddr', 'remote_addr', 'remote_ip', 'client_ip', 'addr', 'address', 'user_agent', 'ua', 'referer', 'referrer',
    'url', 'uri', 'query', 'query_string', 'email', 'mail', 'cookie', 'token', 'session_id', 'fingerprint', 'country', 'city', 'lat', 'lon', 'latitude', 'longitude'];
$privacyViolations = [];
foreach ($schema as $table => $definition) {
    foreach (array_keys($definition['columns']) as $column) {
        $lower = strtolower($column);
        if (in_array($lower, $forbiddenColumns, true) || preg_match('/(^|_)ip(_|$)/', $lower) === 1) {
            $privacyViolations[] = "{$table}.{$column}";
        }
    }
}
check('vie privée : aucune colonne IP / User-Agent / Referer / URL / e-mail / géolocalisation (' . implode(', ', $privacyViolations) . ')', $privacyViolations === []);
check('vie privée : seul un hachage de jeton est stocké (token_hash, jamais token)', isset($schema['guided_links']['columns']['token_hash']) && !isset($schema['guided_links']['columns']['token']));
check('vie privée : visit_key est nullable (effacée à minuit)', strpos($schema['visits']['columns']['visit_key'], 'NULL DEFAULT NULL') !== false && strpos($schema['visits']['columns']['visit_key'], 'NOT NULL') === false);
check('vie privée : login_attempts.visit_key nullable', strpos($schema['login_attempts']['columns']['visit_key'], 'NOT NULL') === false);
check('vie privée : referrer_domain est un domaine (VARCHAR(253), ASCII)', strpos($schema['visits']['columns']['referrer_domain'], 'VARCHAR(253) CHARACTER SET ascii') === 0);

// Chaque table a une clé primaire ; types des clés étrangères identiques ; suppression en cascade ou mise à NULL attendue
foreach ($schema as $table => $definition) {
    $primaryCount = 0;
    foreach ($definition['indexes'] as $index) {
        if ($index['primary'] && count($index['cols']) >= 1) {
            $primaryCount++;
        }
    }
    check("schéma : {$table} a une seule clé primaire", $primaryCount === 1);
}

$typeOf = static function (string $definition): string {
    return strtoupper(preg_replace('/\s+(CHARACTER SET.*|NOT NULL.*|NULL.*|AUTO_INCREMENT.*|DEFAULT.*)$/', '', $definition));
};
check('clés étrangères : 2 déclarées', count($foreignKeys) === 2);
foreach ($foreignKeys as $fk) {
    $childType = $typeOf($schema[$fk['table']]['columns'][$fk['column']] ?? '');
    $parentType = $typeOf($schema[$fk['ref_table']]['columns'][$fk['ref_column']] ?? '');
    check("clé étrangère {$fk['table']}.{$fk['column']} -> {$fk['ref_table']}.{$fk['ref_column']} : même type ({$childType})", $childType !== '' && $childType === $parentType);
    if ($fk['on_delete'] === 'SET NULL') {
        check("clé étrangère {$fk['table']}.{$fk['column']} : colonne nullable pour SET NULL", strpos($schema[$fk['table']]['columns'][$fk['column']], 'NOT NULL') === false);
    }
}

// Longueur des index : InnoDB (DYNAMIC) accepte 3072 octets au plus
$bytesOf = static function (string $definition): int {
    $type = strtoupper($definition);
    $ascii = strpos($type, 'CHARACTER SET ASCII') !== false;
    if (preg_match('/^(VARCHAR|CHAR)\((\d+)\)/', $type, $m) === 1) {
        $chars = (int) $m[2];
        return $chars * ($ascii ? 1 : 4) + ($m[1] === 'VARCHAR' ? 2 : 0);
    }
    if (strpos($type, 'ENUM(') === 0) {
        return 1;
    }
    foreach (['BIGINT' => 8, 'INT' => 4, 'SMALLINT' => 2, 'TINYINT' => 1, 'DATETIME' => 5, 'DATE' => 3] as $name => $size) {
        if (preg_match('/^' . $name . '\b/', $type) === 1) {
            return $size;
        }
    }
    return 100000; // type inconnu : fait échouer le contrôle
};
$maxIndex = 0;
foreach ($schema as $table => $definition) {
    foreach ($definition['indexes'] as $index) {
        $size = 0;
        foreach ($index['cols'] as $column) {
            $size += $bytesOf($definition['columns'][$column] ?? 'INCONNU');
        }
        $maxIndex = max($maxIndex, $size);
        check("index {$table}(" . implode(',', $index['cols']) . ") : {$size} octets (<= 3072)", $size <= 3072);
    }
}
check('index : le plus long reste très en deçà de la limite', $maxIndex < 1100);
check('index : les colonnes indexées existent', (function () use ($schema): bool {
    foreach ($schema as $definition) {
        foreach ($definition['indexes'] as $index) {
            foreach ($index['cols'] as $column) {
                if (!isset($definition['columns'][$column])) {
                    return false;
                }
            }
        }
    }
    return true;
})());

// Compatibilité phpMyAdmin 4.9 / MariaDB / MySQL : aucune construction risquée
$joined = implode("\n", array_merge(...array_values($allStatements)));
check('compat : pas de DEFAULT CURRENT_TIMESTAMP (fuseau de session)', stripos($joined, 'CURRENT_TIMESTAMP') === false);
check('compat : pas de DELIMITER, TRIGGER, PROCEDURE, FUNCTION, EVENT', preg_match('/\b(DELIMITER|TRIGGER|PROCEDURE|FUNCTION|EVENT)\b/i', $joined) !== 1);
check('compat : pas de JSON, GENERATED, INVISIBLE, SEQUENCE', preg_match('/\b(JSON|GENERATED|INVISIBLE|SEQUENCE)\b/i', $joined) !== 1);
check('compat : pas de collation uca1400 (inconnue de phpMyAdmin 4.9)', stripos($joined, 'uca1400') === false);
check('compat : toutes les tables en InnoDB, utf8mb4_unicode_ci', preg_match_all('/ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci/', $joined) === 8);
check('compat : CREATE TABLE IF NOT EXISTS partout', preg_match_all('/CREATE TABLE IF NOT EXISTS/', $joined) === 8 && preg_match_all('/CREATE TABLE/', $joined) === 8);
check('compat : heures enregistrées en UTC (UTC_TIMESTAMP)', substr_count($joined, 'UTC_TIMESTAMP()') === 2 && stripos($joined, 'NOW()') === false);

// ============================================================================
// 5. Migrateur (sur SQLite en mémoire, avec de fausses migrations)
// ============================================================================
class SqliteMigrator extends DashboardMigrator
{
    protected function tableExists(PDO $pdo): bool
    {
        return (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'schema_migrations'")->fetchColumn() > 0;
    }
}

$newSqlite = static function (): PDO {
    return new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
};
$record = static fn (string $version): string => "INSERT INTO schema_migrations (version, description, applied_at) VALUES ('{$version}', 'x', datetime('now'));\n";
$bootstrap = "CREATE TABLE schema_migrations (version TEXT PRIMARY KEY, description TEXT, applied_at TEXT);\n";

$dir = $work . '/mig-ok';
mkdir($dir);
file_put_contents($dir . '/001_first.sql', "-- première\n" . $bootstrap . "CREATE TABLE a (x INTEGER);\n" . $record('001'));
file_put_contents($dir . '/002_second.sql', "CREATE TABLE b (y INTEGER);\n" . $record('002'));
$m = new SqliteMigrator($dir);
$pdo = $newSqlite();

check('migrateur : base vierge -> rien d\'appliqué', $m->appliedVersions($pdo) === []);
check('migrateur : base vierge -> 2 en attente', array_column($m->pending($pdo), 'version') === ['001', '002']);
$lines = [];
$done = $m->migrate($pdo, static function (string $line) use (&$lines): void {
    $lines[] = $line;
});
check('migrateur : applique 001 et 002', $done === ['001', '002'] && count($lines) === 2);
check('migrateur : compte rendu lisible', strpos($lines[0], '001') !== false && strpos($lines[0], '001_first.sql') !== false);
check('migrateur : tables créées', (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE name IN ('a','b')")->fetchColumn() === 2);
check('migrateur : versions enregistrées', $m->appliedVersions($pdo) === ['001', '002']);
check('migrateur : deuxième passage = rien à faire', $m->migrate($pdo) === [] && $m->pending($pdo) === []);

file_put_contents($dir . '/003_third.sql', "CREATE TABLE c (z INTEGER);\n" . $record('003'));
check('migrateur : nouvelle migration détectée', array_column($m->pending($pdo), 'version') === ['003']);
check('migrateur : applique seulement la nouvelle', $m->migrate($pdo) === ['003']);

// Base en avance sur les fichiers
unlink($dir . '/003_third.sql');
check('migrateur : base en avance sur les fichiers -> erreur', throwsRuntime(static function () use ($m, $pdo): void {
    $m->pending($pdo);
}, 'synchronisés'));

// Trou de numérotation, nom invalide
$dirGap = $work . '/mig-gap';
mkdir($dirGap);
file_put_contents($dirGap . '/001_a.sql', $bootstrap . $record('001'));
file_put_contents($dirGap . '/003_c.sql', $record('003'));
check('migrateur : trou de numérotation refusé', throwsRuntime(static function () use ($dirGap): void {
    (new SqliteMigrator($dirGap))->listMigrations();
}, '002 attendu'));

$dirBadName = $work . '/mig-badname';
mkdir($dirBadName);
file_put_contents($dirBadName . '/001_Majuscule.sql', $bootstrap . $record('001'));
check('migrateur : nom invalide refusé', throwsRuntime(static function () use ($dirBadName): void {
    (new SqliteMigrator($dirBadName))->listMigrations();
}, 'invalide'));

$dirNoStart = $work . '/mig-nostart';
mkdir($dirNoStart);
file_put_contents($dirNoStart . '/002_a.sql', $bootstrap . $record('002'));
check('migrateur : doit commencer à 001', throwsRuntime(static function () use ($dirNoStart): void {
    (new SqliteMigrator($dirNoStart))->listMigrations();
}, '001 attendu'));

check('migrateur : dossier vide = aucune migration', (function () use ($work): bool {
    $d = $work . '/mig-empty';
    mkdir($d);
    return (new SqliteMigrator($d))->listMigrations() === [];
})());

// Fichier invalide : rien n'est exécuté, même pour les fichiers valides qui le précèdent
$dirInvalid = $work . '/mig-invalid';
mkdir($dirInvalid);
file_put_contents($dirInvalid . '/001_a.sql', $bootstrap . "CREATE TABLE a (x INTEGER);\n" . $record('001'));
file_put_contents($dirInvalid . '/002_b.sql', "CREATE TABLE b (y INTEGER);\n"); // oublie de s'enregistrer
$mi = new SqliteMigrator($dirInvalid);
$pdoInvalid = $newSqlite();
check('migrateur : migration qui ne s\'enregistre pas -> refusée', throwsRuntime(static function () use ($mi, $pdoInvalid): void {
    $mi->migrate($pdoInvalid);
}, 'Migration 002'));
check('migrateur : rien n\'a été exécuté (contrôle avant la première instruction)', (int) $pdoInvalid->query("SELECT COUNT(*) FROM sqlite_master")->fetchColumn() === 0);

// Échec à mi-parcours : message précis, instructions précédentes déjà appliquées
$dirFail = $work . '/mig-fail';
mkdir($dirFail);
file_put_contents($dirFail . '/001_a.sql', $bootstrap . "CREATE TABLE ok_table (x INTEGER);\nCREATE TABLE ok_table (x INTEGER);\nCREATE TABLE never (x INTEGER);\n" . $record('001'));
$mf = new SqliteMigrator($dirFail);
$pdoFail = $newSqlite();
$failMessage = '';
try {
    $mf->migrate($pdoFail);
} catch (RuntimeException $e) {
    $failMessage = $e->getMessage();
}
check('migrateur : échec -> indique l\'instruction (3 sur 5)', strpos($failMessage, 'instruction 3 sur 5') !== false);
check('migrateur : échec -> prévient que le reste est appliqué', strpos($failMessage, 'déjà appliquées') !== false);
check('migrateur : échec -> la suite n\'est pas exécutée, la version n\'est pas enregistrée', (int) $pdoFail->query("SELECT COUNT(*) FROM sqlite_master WHERE name = 'never'")->fetchColumn() === 0 && (int) $pdoFail->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn() === 0);

// Connexion qui ne lève pas d'exceptions : refusée
check('migrateur : refuse une connexion silencieuse', throwsRuntime(static function () use ($m): void {
    $silent = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT]);
    $m->migrate($silent);
}, 'exceptions'));

// ============================================================================
// 6. Connexion silencieuse et disjoncteur
// ============================================================================
$server = stream_socket_server('tcp://127.0.0.1:0');
$closedPort = (int) substr((string) stream_socket_get_name($server, false), strrpos((string) stream_socket_get_name($server, false), ':') + 1);
fclose($server); // le port est maintenant fermé : connexion refusée immédiatement

$dbConfig = [
    'db_host'     => '127.0.0.1',
    'db_port'     => $closedPort,
    'db_name'     => 'dbname_x',
    'db_user'     => 'u_secret_user',
    'db_password' => 'p@ss-SECRET-value',
];

$cooldown = $work . DIRECTORY_SEPARATOR . 'cooldown-test';
$logFile = $work . DIRECTORY_SEPARATOR . 'php-errors.log';
$previousLog = ini_get('error_log');
ini_set('log_errors', '1');
ini_set('error_log', $logFile);

$strictThrows = false;
try {
    DashboardDatabase::connect($dbConfig);
} catch (PDOException $e) {
    $strictThrows = true;
}
check('connexion stricte : exception si la base est injoignable', $strictThrows);

$start = microtime(true);
$first = DashboardDatabase::connectQuietly($dbConfig, $cooldown);
check('connexion silencieuse : null et aucune exception', $first === null);
check('connexion silencieuse : disjoncteur enclenché', file_exists($cooldown) && DashboardDatabase::isCoolingDown($cooldown));
check('connexion silencieuse : une ligne de journal', substr_count((string) @file_get_contents($logFile), 'dashboard : base de données indisponible') === 1);

$second = DashboardDatabase::connectQuietly($dbConfig, $cooldown);
check('disjoncteur : 2e appel immédiat, null', $second === null);
check('disjoncteur : 2e appel n\'a pas tenté de connexion (toujours 1 ligne de journal)', substr_count((string) @file_get_contents($logFile), 'dashboard : base de données indisponible') === 1);

$logContent = (string) @file_get_contents($logFile);
foreach (['u_secret_user', 'p@ss-SECRET-value', '127.0.0.1', 'dbname_x'] as $secretPart) {
    check("journal : ne contient pas « {$secretPart} »", strpos($logContent, $secretPart) === false);
}
check('journal : code d\'erreur présent', preg_match('/\(code (\d+|[A-Za-z0-9?]+)\)/', $logContent) === 1);

touch($cooldown, time() - 61);
$third = DashboardDatabase::connectQuietly($dbConfig, $cooldown);
check('disjoncteur : après 61 s, nouvelle tentative (2 lignes de journal)', $third === null && substr_count((string) @file_get_contents($logFile), 'dashboard : base de données indisponible') === 2);

$now = 1_000_000;
touch($cooldown, $now - 59);
check('disjoncteur : 59 s -> enclenché', DashboardDatabase::isCoolingDown($cooldown, $now));
touch($cooldown, $now - 60);
check('disjoncteur : 60 s -> relâché', !DashboardDatabase::isCoolingDown($cooldown, $now));
touch($cooldown, $now + 30);
check('disjoncteur : date dans le futur -> relâché (pas de blocage définitif)', !DashboardDatabase::isCoolingDown($cooldown, $now));
check('disjoncteur : fichier absent -> relâché', !DashboardDatabase::isCoolingDown($work . '/absent'));

$target = $work . DIRECTORY_SEPARATOR . 'cible-lien';
file_put_contents($target, 'x');
touch($target, 946684800);
$link = $work . DIRECTORY_SEPARATOR . 'lien-cooldown';
if (@symlink($target, $link)) {
    DashboardDatabase::startCooldown($link);
    check('disjoncteur : ne suit pas un lien symbolique', filemtime($target) === 946684800);
}
DashboardDatabase::startCooldown($work . '/dossier-inexistant/x');
check('disjoncteur : dossier non inscriptible -> échec silencieux', true);

$nameA = DashboardDatabase::cooldownFile($dbConfig);
$nameB = DashboardDatabase::cooldownFile(['db_name' => 'autre'] + $dbConfig);
check('cooldownFile : propre à la base', $nameA !== $nameB && strpos($nameA, 'jt-dashboard-down-') !== false);
check('cooldownFile : ne contient ni mot de passe ni identifiant', strpos($nameA, 'u_secret_user') === false && strpos($nameA, 'SECRET') === false);

// Un hôte qui ne répond pas : la page n'attend jamais plus que le délai de connexion (+ marge)
$slowCooldown = $work . DIRECTORY_SEPARATOR . 'cooldown-slow';
$t0 = microtime(true);
$slow = DashboardDatabase::connectQuietly(['db_host' => '10.255.255.1', 'db_port' => 3306] + $dbConfig, $slowCooldown);
$elapsed = microtime(true) - $t0;
check(sprintf('hôte muet : null en %.1f s (limite %d s + marge)', $elapsed, DashboardDatabase::CONNECT_TIMEOUT_SECONDS), $slow === null && $elapsed < DashboardDatabase::CONNECT_TIMEOUT_SECONDS + 1.5);

ini_set('error_log', $previousLog === false ? '' : $previousLog);
removeTree($work);

echo "\nRésultat : {$ok} OK, {$ko} échec(s)\n";
exit($ko === 0 ? 0 : 1);
