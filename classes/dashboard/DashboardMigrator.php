<?php
declare(strict_types=1);

/**
 * Fichier : classes/dashboard/DashboardMigrator.php
 * Rôle : Applique dans l'ordre les migrations SQL de database/migrations/ (001_nom.sql, 002_nom.sql...).
 *
 * Deux façons d'appliquer une migration, qui donnent le même résultat :
 *   - php database/migrate.php up        (en local)
 *   - importer le fichier .sql dans phpMyAdmin (en production : la base IONOS n'est pas joignable de l'extérieur)
 * Dans les deux cas, la DERNIÈRE instruction du fichier enregistre sa version dans schema_migrations :
 * c'est cette table qui dit où en est la base.
 *
 * Règles d'écriture d'un fichier de migration :
 *   - encodage UTF-8 sans BOM, instructions terminées par « ; », jamais de DELIMITER ni de commentaire /*! ... *\/
 *   - commentaires « -- » (un espace après les tirets) sur leur propre ligne, sans « ; » dedans
 *   - dernière instruction : INSERT INTO schema_migrations (version, description, applied_at) VALUES ('NNN', ...)
 * Le MySQL/MariaDB ne peut pas annuler un CREATE ou un ALTER : une migration interrompue à mi-chemin se corrige à la main.
 *
 * Compatible PHP 8.0.
 */
class DashboardMigrator
{
    private string $directory;

    public function __construct(string $directory)
    {
        $this->directory = rtrim($directory, '/\\');
    }

    /**
     * Migrations disponibles, dans l'ordre.
     *
     * @return list<array{version:string,name:string,path:string}>
     * @throws RuntimeException nom de fichier invalide, numéro manquant ou en double
     */
    public function listMigrations(): array
    {
        $files = glob($this->directory . '/*.sql');
        if ($files === false) {
            throw new RuntimeException('Dossier des migrations illisible : ' . $this->directory);
        }
        sort($files, SORT_STRING);

        $migrations = [];
        $expected = 1;
        foreach ($files as $path) {
            $name = basename($path);
            if (preg_match('/^(\d{3})_([a-z0-9_]+)\.sql$/', $name, $matches) !== 1) {
                throw new RuntimeException('Nom de migration invalide : « ' . $name . ' » (attendu : 001_nom_en_minuscules.sql).');
            }

            if ((int) $matches[1] !== $expected) {
                throw new RuntimeException(sprintf(
                    'Numérotation des migrations cassée : « %s » trouvé, %03d attendu (pas de trou ni de doublon).',
                    $name,
                    $expected
                ));
            }

            $migrations[] = ['version' => $matches[1], 'name' => $name, 'path' => $path];
            $expected++;
        }

        return $migrations;
    }

    /**
     * Découpe un fichier SQL en instructions (sans le « ; » final).
     *
     * @return list<string>
     * @throws RuntimeException fichier non conforme aux règles ci-dessus
     */
    public static function splitStatements(string $sql): array
    {
        if (strncmp($sql, "\xEF\xBB\xBF", 3) === 0) {
            throw new RuntimeException('Le fichier SQL ne doit pas commencer par un BOM UTF-8 (phpMyAdmin le lirait comme du SQL).');
        }

        $sql = str_replace(["\r\n", "\r"], "\n", $sql);
        $length = strlen($sql);
        $statements = [];
        $current = '';
        $i = 0;

        while ($i < $length) {
            $char = $sql[$i];
            $next = ($i + 1 < $length) ? $sql[$i + 1] : '';

            // Commentaire de ligne : « -- » suivi d'un blanc ou de la fin de ligne, ou « # »
            $dashComment = $char === '-' && $next === '-'
                && ($i + 2 >= $length || $sql[$i + 2] === ' ' || $sql[$i + 2] === "\t" || $sql[$i + 2] === "\n");
            if ($dashComment || $char === '#') {
                $end = strpos($sql, "\n", $i);
                $i = ($end === false) ? $length : $end;
                continue;
            }

            // Commentaire /* ... */ (les commentaires exécutables /*! ... */ sont refusés : on ne les ignore pas en silence)
            if ($char === '/' && $next === '*') {
                if (($i + 2 < $length) && $sql[$i + 2] === '!') {
                    throw new RuntimeException('Commentaire exécutable « /*! » non géré : réécrivez l\'instruction.');
                }
                $end = strpos($sql, '*/', $i + 2);
                if ($end === false) {
                    throw new RuntimeException('Commentaire « /* » non fermé.');
                }
                $current .= ' ';
                $i = $end + 2;
                continue;
            }

            // Chaîne ou identifiant entre guillemets : le « ; » qu'il contient ne termine rien
            if ($char === "'" || $char === '"' || $char === '`') {
                $j = $i + 1;
                while (true) {
                    if ($j >= $length) {
                        throw new RuntimeException('Chaîne ou identifiant non fermé (guillemet ' . $char . ').');
                    }
                    $c = $sql[$j];
                    if ($c === '\\' && $char !== '`') {
                        $j += 2;
                        continue;
                    }
                    if ($c === $char) {
                        if ($j + 1 < $length && $sql[$j + 1] === $char) {
                            $j += 2; // guillemet doublé : littéral
                            continue;
                        }
                        break;
                    }
                    $j++;
                }
                $current .= substr($sql, $i, $j - $i + 1);
                $i = $j + 1;
                continue;
            }

            if ($char === ';') {
                $statement = trim($current);
                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $current = '';
                $i++;
                continue;
            }

            $current .= $char;
            $i++;
        }

        if (trim($current) !== '') {
            throw new RuntimeException('La dernière instruction n\'est pas terminée par « ; » (fichier tronqué ?).');
        }

        foreach ($statements as $statement) {
            if (preg_match('/^DELIMITER\b/i', $statement) === 1) {
                throw new RuntimeException('DELIMITER n\'est pas géré (importation phpMyAdmin) : pas de procédure stockée dans les migrations.');
            }
        }

        return $statements;
    }

    /**
     * Vérifie qu'une migration se termine par l'enregistrement de SA version, et par rien d'autre.
     *
     * @param list<string> $statements
     * @throws RuntimeException
     */
    public static function assertRecordsItself(string $version, array $statements): void
    {
        if ($statements === []) {
            throw new RuntimeException('Migration ' . $version . ' : fichier vide.');
        }

        $pattern = '/^INSERT\s+INTO\s+schema_migrations\s*\(\s*version\s*,\s*description\s*,\s*applied_at\s*\)\s*VALUES\s*\(\s*\'(\d{3})\'/i';

        $count = 0;
        foreach ($statements as $statement) {
            if (preg_match('/schema_migrations/i', $statement) === 1 && preg_match('/^INSERT\b/i', $statement) === 1) {
                $count++;
            }
        }

        $last = end($statements);
        if ($count !== 1 || preg_match($pattern, $last, $matches) !== 1 || $matches[1] !== $version) {
            throw new RuntimeException(sprintf(
                'Migration %s : la dernière instruction doit être « INSERT INTO schema_migrations (version, description, applied_at) VALUES (\'%s\', ...) », et la seule de ce type.',
                $version,
                $version
            ));
        }
    }

    /**
     * Versions déjà appliquées (vide si schema_migrations n'existe pas encore).
     *
     * @return list<string>
     */
    public function appliedVersions(PDO $pdo): array
    {
        if (!$this->tableExists($pdo)) {
            return [];
        }

        $rows = $pdo->query('SELECT version FROM schema_migrations ORDER BY version')->fetchAll(PDO::FETCH_COLUMN);

        return array_map('strval', $rows);
    }

    /**
     * Migrations restant à appliquer.
     *
     * @return list<array{version:string,name:string,path:string}>
     * @throws RuntimeException base en avance sur les fichiers, ou migrations appliquées dans le désordre
     */
    public function pending(PDO $pdo): array
    {
        $migrations = $this->listMigrations();
        $applied = $this->appliedVersions($pdo);

        foreach ($applied as $index => $version) {
            if (!isset($migrations[$index]) || $migrations[$index]['version'] !== $version) {
                throw new RuntimeException(sprintf(
                    'La base indique la migration %s, qui ne correspond à aucun fichier à cette place : base et dossier database/migrations/ ne sont pas synchronisés.',
                    $version
                ));
            }
        }

        return array_slice($migrations, count($applied));
    }

    /**
     * Applique les migrations en attente, dans l'ordre. S'arrête à la première erreur.
     *
     * @param callable(string):void|null $log reçoit une ligne de compte rendu par migration appliquée
     * @return list<string> versions appliquées pendant cet appel
     * @throws RuntimeException
     */
    public function migrate(PDO $pdo, ?callable $log = null): array
    {
        if ($pdo->getAttribute(PDO::ATTR_ERRMODE) !== PDO::ERRMODE_EXCEPTION) {
            throw new RuntimeException('La connexion doit être en mode exceptions (PDO::ERRMODE_EXCEPTION) : une erreur SQL passerait inaperçue.');
        }

        $pending = $this->pending($pdo);

        // Tout est lu et contrôlé AVANT la première instruction : un fichier invalide n'entame pas la base
        $plans = [];
        foreach ($pending as $migration) {
            $sql = file_get_contents($migration['path']);
            if ($sql === false) {
                throw new RuntimeException('Migration ' . $migration['version'] . ' : fichier illisible.');
            }

            try {
                $statements = self::splitStatements($sql);
                self::assertRecordsItself($migration['version'], $statements);
            } catch (RuntimeException $e) {
                throw new RuntimeException('Migration ' . $migration['version'] . ' (' . $migration['name'] . ') : ' . $e->getMessage());
            }

            $plans[] = ['migration' => $migration, 'statements' => $statements];
        }

        $applied = [];
        foreach ($plans as $plan) {
            $total = count($plan['statements']);
            foreach ($plan['statements'] as $position => $statement) {
                try {
                    $pdo->exec($statement);
                } catch (PDOException $e) {
                    throw new RuntimeException(sprintf(
                        'Migration %s : échec à l\'instruction %d sur %d (%s). Les instructions précédentes sont déjà appliquées '
                        . 'et ne peuvent pas être annulées : corrigez la base à la main (phpMyAdmin) avant de relancer.',
                        $plan['migration']['version'],
                        $position + 1,
                        $total,
                        $e->getMessage()
                    ));
                }
            }

            $applied[] = $plan['migration']['version'];
            if ($log !== null) {
                $log(sprintf('Migration %s appliquée (%s, %d instructions)', $plan['migration']['version'], $plan['migration']['name'], $total));
            }
        }

        return $applied;
    }

    /** La table schema_migrations existe-t-elle dans la base courante ? (surchargée par les tests, qui utilisent SQLite) */
    protected function tableExists(PDO $pdo): bool
    {
        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
        );
        $statement->execute(['schema_migrations']);

        return (int) $statement->fetchColumn() > 0;
    }
}
