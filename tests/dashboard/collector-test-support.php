<?php
declare(strict_types=1);

/**
 * Fichier : tests/dashboard/collector-test-support.php
 * Rôle : Outils communs aux tests de la collecte (G4b). Ce fichier n'est pas un test : il est chargé par
 *   - test_visit_collector.php (SQLite, sans serveur SQL),
 *   - test_collector_mysql.php (base jetable MariaDB ou MySQL),
 * qui exécutent les MÊMES scénarios (collector-scenarios.php) sur deux moteurs différents.
 *
 * Contenu :
 *   - collectorSqliteSchema() : traduit les VRAIES migrations de database/migrations/ en schéma SQLite strict
 *     (longueurs, valeurs d'ENUM, entiers non signés, ASCII, formats de date, clés uniques, clés étrangères).
 *     Le schéma de test ne peut donc pas dériver du schéma réel : une migration qui change une colonne change le test.
 *   - CollectorTestPdo : une connexion PDO qui sait échouer (interblocage, erreur) ou « rater » une mise à jour,
 *     pour tester les chemins que seule une vraie concurrence provoquerait.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../classes/dashboard/DashboardMigrator.php';

/**
 * Instructions SQLite (CREATE TABLE) équivalentes aux migrations MySQL.
 *
 * @return list<string>
 * @throws RuntimeException instruction de migration que le traducteur ne connaît pas (à compléter ici, volontairement)
 */
function collectorSqliteSchema(string $migrationsDir): array
{
    $files = glob(rtrim($migrationsDir, '/\\') . '/*.sql');
    if ($files === false || $files === []) {
        throw new RuntimeException('Aucune migration trouvée dans ' . $migrationsDir);
    }
    sort($files);

    $tables = [];       // table => ['columns' => list<string>, 'extra' => list<string>, 'auto' => bool]
    $alters = [];       // list<array{0:string,1:string}> table, contrainte

    foreach ($files as $file) {
        foreach (DashboardMigrator::splitStatements((string) file_get_contents($file)) as $statement) {
            if (preg_match('/^INSERT INTO schema_migrations\b/i', $statement) === 1) {
                continue;
            }

            if (preg_match('/^CREATE TABLE IF NOT EXISTS (\w+) \((.*)\) ENGINE=/s', $statement, $m) === 1) {
                $tables[$m[1]] = collectorSqliteTable($m[2]);
                continue;
            }

            if (preg_match('/^ALTER TABLE (\w+) ADD CONSTRAINT \w+ (FOREIGN KEY .+)$/s', $statement, $m) === 1) {
                $alters[] = [$m[1], trim($m[2])];
                continue;
            }

            throw new RuntimeException('Instruction de migration non traduite en SQLite : ' . substr($statement, 0, 60));
        }
    }

    foreach ($alters as [$table, $constraint]) {
        if (!isset($tables[$table])) {
            throw new RuntimeException('ALTER TABLE sur une table inconnue : ' . $table);
        }
        $tables[$table]['extra'][] = $constraint;
    }

    $statements = [];
    foreach ($tables as $name => $table) {
        $statements[] = 'CREATE TABLE ' . $name . " (\n  " . implode(",\n  ", array_merge($table['columns'], $table['extra'])) . "\n)";
    }

    return $statements;
}

/**
 * @return array{columns:list<string>,extra:list<string>,auto:bool}
 */
function collectorSqliteTable(string $body): array
{
    $columns = [];
    $extra   = [];
    $auto    = false;
    $pk      = null;

    foreach (explode("\n", $body) as $line) {
        $line = rtrim(trim($line), ',');
        if ($line === '') {
            continue;
        }

        if (preg_match('/^PRIMARY KEY \(([^)]+)\)$/', $line, $m) === 1) {
            $pk = $m[1];
        } elseif (preg_match('/^UNIQUE KEY \w+ \(([^)]+)\)$/', $line, $m) === 1) {
            $extra[] = 'UNIQUE (' . $m[1] . ')';
        } elseif (preg_match('/^KEY \w+ \(([^)]+)\)$/', $line) === 1) {
            continue; // simple index : sans effet sur le comportement
        } elseif (preg_match('/^CONSTRAINT \w+ (FOREIGN KEY .+)$/', $line, $m) === 1) {
            $extra[] = $m[1];
        } else {
            [$definition, $isAuto] = collectorSqliteColumn($line);
            $columns[] = $definition;
            $auto = $auto || $isAuto;
        }
    }

    if ($pk !== null && !$auto) {
        $extra[] = 'PRIMARY KEY (' . $pk . ')';
    }

    return ['columns' => $columns, 'extra' => $extra, 'auto' => $auto];
}

/**
 * Colonne MySQL -> colonne SQLite avec les contrôles que fait le mode strict de MySQL.
 *
 * @return array{0:string,1:bool} définition, true si la colonne est AUTO_INCREMENT
 */
function collectorSqliteColumn(string $line): array
{
    if (preg_match('/^(\w+) (CHAR|VARCHAR|BIGINT|INT|SMALLINT|TINYINT|DATETIME|DATE|ENUM)(?=[ (])(?:\(([^)]*)\))?( UNSIGNED)?(.*)$/', $line, $m) !== 1) {
        throw new RuntimeException('Colonne non traduite en SQLite : ' . $line);
    }

    $name      = $m[1];
    $type      = $m[2];
    $arguments = $m[3];
    $unsigned  = $m[4] !== '';
    $rest      = $m[5];

    if (strpos($rest, 'AUTO_INCREMENT') !== false) {
        return [$name . ' INTEGER PRIMARY KEY AUTOINCREMENT', true];
    }

    $checks = [];
    switch ($type) {
        case 'CHAR':
        case 'VARCHAR':
            $sqlType  = 'TEXT';
            $checks[] = 'length(' . $name . ') <= ' . (int) $arguments;
            if (strpos($rest, 'CHARACTER SET ascii') !== false) {
                $checks[] = $name . " NOT GLOB '*[^ -~]*'";
            }
            break;
        case 'ENUM':
            $sqlType  = 'TEXT';
            $checks[] = $name . ' IN (' . $arguments . ')';
            break;
        case 'DATE':
            $sqlType  = 'TEXT';
            $checks[] = $name . " GLOB '[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]'";
            break;
        case 'DATETIME':
            $sqlType  = 'TEXT';
            $checks[] = $name . " GLOB '[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9] [0-9][0-9]:[0-9][0-9]:[0-9][0-9]'";
            break;
        default:
            $sqlType = 'INTEGER';
            if ($unsigned) {
                $checks[] = $name . ' >= 0';
            }
    }

    $definition = $name . ' ' . $sqlType;
    if (strpos($rest, 'NOT NULL') !== false) {
        $definition .= ' NOT NULL';
    }
    if (preg_match('/DEFAULT (NULL|\'[^\']*\'|\d+)/', $rest, $d) === 1) {
        $definition .= ' DEFAULT ' . $d[1];
    }
    foreach ($checks as $check) {
        $definition .= ' CHECK (' . $name . ' IS NULL OR (' . $check . '))';
    }

    return [$definition, false];
}

/**
 * Connexion PDO de test. Sait :
 *   - échouer sur une requête précise (failOn) : erreur ordinaire, ou interblocage (SQLSTATE 40001) ;
 *   - « rater » une mise à jour (blindUpdate) : la requête ne trouve plus sa ligne, comme si une autre requête
 *     venait de la créer entre-temps. C'est ce qui provoque, sur une ligne qui existe vraiment, le doublon que la
 *     collecte doit absorber.
 */
final class CollectorTestPdo extends PDO
{
    /** @var list<array{pattern:string,times:int,deadlock:bool}> */
    private array $failures = [];

    /** @var list<array{pattern:string,times:int}> */
    private array $blind = [];

    /** Nombre de requêtes que failOn() a fait échouer. */
    public int $failed = 0;

    /** Fait échouer les $times prochaines requêtes dont le SQL correspond à $pattern (expression régulière). */
    public function failOn(string $pattern, int $times = 1, bool $deadlock = false): void
    {
        $this->failures[] = ['pattern' => $pattern, 'times' => $times, 'deadlock' => $deadlock];
    }

    /** Les $times prochaines requêtes correspondant à $pattern ne trouvent aucune ligne (WHERE 1 = 0 AND ...). */
    public function blindUpdate(string $pattern, int $times = 1): void
    {
        $this->blind[] = ['pattern' => $pattern, 'times' => $times];
    }

    #[\ReturnTypeWillChange]
    public function prepare($query, $options = [])
    {
        foreach ($this->failures as $index => $failure) {
            if ($failure['times'] > 0 && preg_match($failure['pattern'], $query) === 1) {
                $this->failures[$index]['times']--;
                $this->failed++;

                $e = new PDOException($failure['deadlock'] ? 'Deadlock found when trying to get lock' : 'Erreur simulée');
                $e->errorInfo = $failure['deadlock'] ? ['40001', 1213, 'Deadlock'] : ['HY000', 1105, 'Simulated'];
                throw $e;
            }
        }

        foreach ($this->blind as $index => $blind) {
            if ($blind['times'] > 0 && preg_match($blind['pattern'], $query) === 1) {
                $this->blind[$index]['times']--;
                $query = (string) preg_replace('/\bWHERE\b/', 'WHERE 1 = 0 AND', $query, 1);
                break;
            }
        }

        return parent::prepare($query, $options);
    }
}
