<?php
declare(strict_types=1);

/**
 * Fichier : classes/dashboard/VisitCollector.php
 * Rôle : Écrit une page vue dans la base du dashboard, selon les règles de docs/dashboard-vie-privee.md.
 *
 * Ce que fait record() pour une requête (dans une seule transaction) :
 *   1. ignore tout ce qui n'est pas un GET de code 200, 301, 404 ou 500 ;
 *   2. un fichier statique introuvable (image, feuille de style...) n'est pas une page vue : seulement une ligne
 *      d'erreur « not_found », pour repérer les liens cassés ;
 *   3. compte la page dans daily_stats (TOUTES les pages, sans aucun identifiant) ;
 *   4. note les 404 et les 500 dans errors (une ligne par jour, type et chemin) ;
 *   5. sauf DNT, Sec-GPC ou adresse invalide : rattache la page à une visite (clé du jour, 30 minutes) et l'ajoute
 *      au parcours (hits). Seule une page de code 200 ouvre une visite : les 404 d'un scanner qui se fait passer
 *      pour un navigateur ne gonflent donc jamais le nombre de visiteurs, et une 301 est toujours suivie d'une 200.
 * Avant cela, une seule fois par jour, maintain() efface les clés de visite des jours passés et purge les données
 * trop anciennes (13 mois, 90 jours, 30 jours).
 *
 * Aucune adresse IP n'est écrite : elle sert à calculer la clé en mémoire, puis disparaît avec la requête. Les textes
 * venus du visiteur passent par VisitText. SQL volontairement portable (pas de « ON DUPLICATE KEY ») : la logique est
 * testée sur SQLite et sur une vraie base MySQL ou MariaDB avec les mêmes scénarios.
 *
 * Compatible PHP 8.0.
 */
require_once __DIR__ . '/VisitPrivacy.php';
require_once __DIR__ . '/VisitText.php';
require_once __DIR__ . '/VisitClassifier.php';

final class VisitCollector
{
    /** Codes HTTP enregistrés. */
    public const STATUSES = [200, 301, 404, 500];

    /** Conservation du détail (visites et parcours), des erreurs et des tentatives de connexion (docs/dashboard-vie-privee.md, § 8). */
    public const RETENTION_DETAIL = 'P13M';
    public const RETENTION_ERRORS = 'P90D';
    public const RETENTION_LOGIN  = 'P30D';

    /** Sujets réservés aux demandes ignorées par le frein (ils commencent par « [ » : jamais un chemin, qui commence par « / »). */
    public const SUBJECT_NOT_FOUND_BURST = '[rafale de 404 : demandes non détaillées]';
    public const SUBJECT_WRITE_CEILING   = '[plafond d\'écritures atteint : demandes non détaillées]';

    /** Extensions des fichiers statiques du site : leur 404 est un lien cassé, pas une page vue. */
    private const STATIC_EXTENSIONS = 'css|js|mjs|map|png|jpe?g|gif|svg|ico|webp|avif|bmp|woff2?|ttf|otf|eot|mp3|mp4|webm|pdf|txt|xml|json|webmanifest';

    private function __construct()
    {
    }

    /**
     * Requête normalisée, prête pour record() : plus aucune valeur brute du visiteur hors de ce tableau, et dans ce
     * tableau seulement ce dont la collecte a besoin (pas la requête complète, seuls les paramètres utm_* et creation).
     *
     * @param array<string, mixed> $server $_SERVER
     * @param array<string, mixed> $query  $_GET
     * @return array{method:string,status:int,page:string,lang:string,uri:string,ip:string,user_agent:string,referer:string,host:string,utm:array<string,string>,creation:?string,opted_out:bool}
     */
    public static function buildRequest(array $server, array $query, ?string $page, string $lang, int $status): array
    {
        $text = static function ($value, int $max): string {
            return is_string($value) ? substr($value, 0, $max) : '';
        };

        $host = strtolower($text($server['HTTP_HOST'] ?? null, 300));
        $host = (string) preg_replace('/:\d{1,5}$/', '', $host);

        $pageKey = ($page !== null && preg_match('/^[A-Za-z0-9_-]{1,32}$/', $page) === 1) ? $page : ($status === 404 ? '404' : 'unknown');

        $creation = $query['creation'] ?? null;
        $creation = (is_string($creation) && $pageKey === 'creations' && preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/', $creation) === 1)
            ? $creation
            : null;

        return [
            'method'     => strtoupper($text($server['REQUEST_METHOD'] ?? null, 10)),
            'status'     => $status,
            'page'       => $pageKey,
            'lang'       => $lang === 'en' ? 'en' : 'fr',
            'uri'        => $text($server['REQUEST_URI'] ?? null, 2048),
            'ip'         => $text($server['REMOTE_ADDR'] ?? null, 64),
            'user_agent' => $text($server['HTTP_USER_AGENT'] ?? null, 512),
            'referer'    => $text($server['HTTP_REFERER'] ?? null, 2048),
            'host'       => $host,
            'utm'        => VisitPrivacy::utm($query),
            'creation'   => $creation,
            'opted_out'  => VisitClassifier::optedOut($server),
        ];
    }

    /** Vrai si la requête mérite d'être examinée : un GET dont le code est 200, 301, 404 ou 500. */
    public static function wanted(array $request): bool
    {
        return ($request['method'] ?? '') === 'GET' && in_array($request['status'] ?? 0, self::STATUSES, true);
    }

    /**
     * Enregistre une requête.
     *
     * @param string $secret secret du serveur (binaire, 32 octets au moins) : sert au sel du jour
     * @param array<string, mixed> $request résultat de buildRequest()
     * @return string ce qui a été écrit : skipped, static_404, counted (compteur seul), visit_started ou visit_continued
     * @throws Throwable erreur de base de données (l'appelant l'attrape : la page n'est jamais cassée)
     */
    public static function record(PDO $pdo, string $secret, array $request, int $now): string
    {
        if (!self::wanted($request)) {
            return 'skipped';
        }

        $status   = (int) $request['status'];
        $optedOut = (bool) $request['opted_out'];
        $path     = VisitText::path((string) $request['uri']);

        if ($status === 404 && self::looksStatic($path)) {
            if ($optedOut) {
                return 'skipped';
            }
            self::maintain($pdo, $now);
            self::atomically($pdo, function () use ($pdo, $now, $path): void {
                self::bumpError($pdo, VisitPrivacy::day($now), 'not_found', $path, 1, $now);
            });

            return 'static_404';
        }

        self::maintain($pdo, $now);

        return self::atomically($pdo, function () use ($pdo, $secret, $request, $now, $status, $optedOut, $path): string {
            $day    = VisitPrivacy::day($now);
            $client = VisitClassifier::classify((string) $request['user_agent']);

            self::bumpDaily($pdo, $day, $client['profile'], (string) $request['page'], (string) $request['lang'], $status);

            if (!$optedOut && $status === 404) {
                self::bumpError($pdo, $day, 'not_found', $path, 1, $now);
            }
            if (!$optedOut && $status === 500) {
                self::bumpError($pdo, $day, 'server_error', $path, 1, $now);
            }

            // DNT, Sec-GPC (décision D1), redirection ou adresse invalide : le compteur seul
            if ($optedOut || $status === 301) {
                return 'counted';
            }
            $key = VisitPrivacy::key(VisitPrivacy::dailySalt($secret, $day), (string) $request['ip'], (string) $request['user_agent']);
            if ($key === null) {
                return 'counted';
            }

            $visitId = self::findContinuing($pdo, $key, $day, $now);
            if ($visitId !== null) {
                self::execute($pdo, 'UPDATE visits SET last_hit_at = ?, hit_count = hit_count + 1 WHERE visit_id = ?', [self::sqlTime($now), $visitId]);
                self::insertHit($pdo, $visitId, $request, $now);

                return 'visit_continued';
            }

            // Seule une page de code 200 ouvre une visite
            if ($status !== 200) {
                return 'counted';
            }

            $visitId = self::insertVisit($pdo, $key, $day, $client, $request, $now);
            self::insertHit($pdo, $visitId, $request, $now);

            return 'visit_started';
        });
    }

    /**
     * Garde la trace de demandes que le frein a ignorées (rafale de 404 d'un scanner) : une ligne par jour et par type,
     * dont « occurrences » est le nombre de demandes ignorées. Le dashboard peut ainsi afficher « 412 demandes non détaillées ».
     */
    public static function recordDropped(PDO $pdo, string $kind, string $subject, int $count, int $now): void
    {
        if ($count < 1) {
            return;
        }

        self::atomically($pdo, function () use ($pdo, $kind, $subject, $count, $now): void {
            self::bumpError($pdo, VisitPrivacy::day($now), $kind, $subject, $count, $now);
        });
    }

    /**
     * Tâches du jour, exécutées une seule fois par jour (la première requête du jour s'en charge) :
     * effacement des clés de visite des jours passés, puis purge des données trop anciennes.
     * Une erreur y est journalisée sans empêcher d'enregistrer la page.
     */
    public static function maintain(PDO $pdo, int $now): void
    {
        $today = VisitPrivacy::day($now);

        $state = [];
        $stmt = $pdo->query("SELECT name, value FROM settings WHERE name IN ('last_rotation', 'last_purge')");
        foreach ($stmt === false ? [] : $stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $state[(string) $row['name']] = (string) $row['value'];
        }

        if (($state['last_rotation'] ?? '') !== $today) {
            try {
                self::rotate($pdo, $now);
            } catch (Throwable $e) {
                error_log('dashboard : effacement des clés de visite impossible (' . self::describe($e) . ')');
            }
        }

        if (($state['last_purge'] ?? '') !== $today) {
            try {
                self::purge($pdo, $now);
            } catch (Throwable $e) {
                error_log('dashboard : purge impossible (' . self::describe($e) . ')');
            }
        }
    }

    /**
     * Efface les clés de visite des jours passés (visits et login_attempts). Une seule requête du jour y parvient :
     * la première à enregistrer « last_rotation ». Si l'effacement échoue, l'enregistrement est annulé avec lui et la
     * requête suivante réessaie : on ne marque jamais un jour comme fait sans que les clés soient effacées.
     *
     * @return bool true si cette requête a effacé les clés, false si c'était déjà fait aujourd'hui
     */
    public static function rotate(PDO $pdo, int $now): bool
    {
        $today = VisitPrivacy::day($now);

        return self::atomically($pdo, function () use ($pdo, $today, $now): bool {
            if (!self::claim($pdo, 'last_rotation', $today, $now)) {
                return false;
            }

            self::execute($pdo, 'UPDATE visits SET visit_key = NULL WHERE visit_day < ? AND visit_key IS NOT NULL', [$today]);

            // login_attempts n'est utilisée qu'à partir de G5 : son absence ne doit pas empêcher l'effacement des visites
            try {
                self::execute($pdo, 'UPDATE login_attempts SET visit_key = NULL WHERE visit_key IS NOT NULL AND attempted_at < ?', [self::dayStartSql($today)]);
            } catch (PDOException $e) {
                error_log('dashboard : effacement des clés de connexion impossible (' . self::describe($e) . ')');
            }

            return true;
        });
    }

    /**
     * Supprime ce qui dépasse la durée de conservation : visites et parcours (13 mois, les hits partent avec leur visite),
     * erreurs et chemins de 404 (90 jours), tentatives de connexion (30 jours). Les compteurs de daily_stats sont conservés.
     * Les liens de visite guidée seront purgés par G6.
     *
     * @return bool true si cette requête a purgé, false si c'était déjà fait aujourd'hui
     */
    public static function purge(PDO $pdo, int $now): bool
    {
        $today = VisitPrivacy::day($now);

        return self::atomically($pdo, function () use ($pdo, $today, $now): bool {
            if (!self::claim($pdo, 'last_purge', $today, $now)) {
                return false;
            }

            self::execute($pdo, 'DELETE FROM visits WHERE visit_day < ?', [self::cutoffDay($today, self::RETENTION_DETAIL)]);
            self::execute($pdo, 'DELETE FROM errors WHERE error_day < ?', [self::cutoffDay($today, self::RETENTION_ERRORS)]);

            try {
                self::execute($pdo, 'DELETE FROM login_attempts WHERE attempted_at < ?', [self::dayStartSql(self::cutoffDay($today, self::RETENTION_LOGIN))]);
            } catch (PDOException $e) {
                error_log('dashboard : purge des tentatives de connexion impossible (' . self::describe($e) . ')');
            }

            return true;
        });
    }

    /** Description d'une erreur sans donnée : classe et codes seulement, jamais le message (il peut citer une valeur). */
    public static function describe(Throwable $e): string
    {
        if ($e instanceof PDOException && isset($e->errorInfo[0])) {
            return 'PDO ' . preg_replace('/[^0-9A-Za-z]/', '', (string) $e->errorInfo[0]) . '/' . (int) ($e->errorInfo[1] ?? 0);
        }

        return get_class($e);
    }

    // ------------------------------------------------------------------
    // Écritures
    // ------------------------------------------------------------------

    /** Compte une page vue : jour, profil, page, langue et code (compteur sans aucun identifiant). */
    private static function bumpDaily(PDO $pdo, string $day, string $profile, string $page, string $lang, int $status): void
    {
        $params = [$day, $profile, $page, $lang, $status];
        $update = 'UPDATE daily_stats SET hits = hits + 1 WHERE stat_date = ? AND profile = ? AND page = ? AND lang = ? AND status = ?';

        if (self::execute($pdo, $update, $params) > 0) {
            return;
        }

        try {
            self::execute($pdo, 'INSERT INTO daily_stats (stat_date, profile, page, lang, status, hits) VALUES (?, ?, ?, ?, ?, 1)', $params);
        } catch (PDOException $e) {
            // Une autre requête vient de créer la ligne : on l'incrémente. Si la ligne reste introuvable, ce n'était pas
            // un doublon (autre contrainte violée) : l'erreur d'origine remonte, rien n'est perdu en silence.
            if (!self::isDuplicate($e) || self::execute($pdo, $update, $params) < 1) {
                throw $e;
            }
        }
    }

    /** Ajoute $by occurrence(s) à l'erreur du jour (une seule ligne par jour, type et sujet). */
    private static function bumpError(PDO $pdo, string $day, string $kind, string $subject, int $by, int $now): void
    {
        $subject = VisitText::clean($subject, VisitText::MAX_LENGTH);
        $time    = self::sqlTime($now);
        $update  = 'UPDATE errors SET occurrences = occurrences + ?, last_at = ? WHERE error_day = ? AND kind = ? AND subject = ?';

        if (self::execute($pdo, $update, [$by, $time, $day, $kind, $subject]) > 0) {
            return;
        }

        try {
            self::execute(
                $pdo,
                "INSERT INTO errors (error_day, kind, subject, message, occurrences, first_at, last_at) VALUES (?, ?, ?, '', ?, ?, ?)",
                [$day, $kind, $subject, $by, $time, $time]
            );
        } catch (PDOException $e) {
            if (!self::isDuplicate($e) || self::execute($pdo, $update, [$by, $time, $day, $kind, $subject]) < 1) {
                throw $e;
            }
        }
    }

    /** Visite du même visiteur (même clé, même jour) dont la dernière page date de moins de 30 minutes. */
    private static function findContinuing(PDO $pdo, string $key, string $day, int $now): ?int
    {
        $stmt = $pdo->prepare('SELECT visit_id, last_hit_at FROM visits WHERE visit_key = ? AND visit_day = ? ORDER BY last_hit_at DESC, visit_id DESC LIMIT 1');
        $stmt->execute([$key, $day]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return null;
        }

        return VisitPrivacy::continues(self::parseSqlTime((string) $row['last_hit_at']), $now) ? (int) $row['visit_id'] : null;
    }

    /**
     * @param array{profile:string,bot_agent:?string,device_type:string,browser:?string,os:?string} $client
     * @param array<string, mixed> $request
     */
    private static function insertVisit(PDO $pdo, string $key, string $day, array $client, array $request, int $now): int
    {
        $utm   = $request['utm'];
        $time  = self::sqlTime($now);
        $agent = $client['bot_agent'] === null ? null : VisitText::userAgent($client['bot_agent']);

        self::execute(
            $pdo,
            'INSERT INTO visits (visit_key, visit_day, profile, started_at, last_hit_at, hit_count, device_type, browser, os, bot_agent, referrer_domain, utm_source, utm_medium, utm_campaign) '
            . 'VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $key,
                $day,
                $client['profile'],
                $time,
                $time,
                $client['device_type'],
                $client['browser'],
                $client['os'],
                $agent,
                VisitPrivacy::referrerDomain((string) $request['referer'], (string) $request['host']),
                $utm['utm_source'] ?? null,
                $utm['utm_medium'] ?? null,
                $utm['utm_campaign'] ?? null,
            ]
        );

        return (int) $pdo->lastInsertId();
    }

    /** @param array<string, mixed> $request */
    private static function insertHit(PDO $pdo, int $visitId, array $request, int $now): void
    {
        $creation = ((int) $request['status'] === 200) ? $request['creation'] : null;

        self::execute(
            $pdo,
            'INSERT INTO hits (visit_id, hit_at, page, lang, status, creation_id) VALUES (?, ?, ?, ?, ?, ?)',
            [$visitId, self::sqlTime($now), $request['page'], $request['lang'], (int) $request['status'], $creation]
        );
    }

    /**
     * Réserve une tâche quotidienne : vrai pour la seule requête qui fait passer le réglage à aujourd'hui.
     * Dans une transaction, les autres requêtes attendent la fin de la première, puis constatent que c'est fait.
     */
    private static function claim(PDO $pdo, string $name, string $today, int $now): bool
    {
        $time = self::sqlTime($now);

        if (self::execute($pdo, 'UPDATE settings SET value = ?, updated_at = ? WHERE name = ? AND value <> ?', [$today, $time, $name, $today]) > 0) {
            return true;
        }

        if (self::settingExists($pdo, $name)) {
            return false; // déjà à la date du jour
        }

        try {
            self::execute($pdo, 'INSERT INTO settings (name, value, updated_at) VALUES (?, ?, ?)', [$name, $today, $time]);
        } catch (PDOException $e) {
            // Une autre requête vient de créer le réglage : c'est elle qui s'en charge. Sinon, l'erreur remonte.
            if (self::isDuplicate($e) && self::settingExists($pdo, $name)) {
                return false;
            }
            throw $e;
        }

        return true;
    }

    // ------------------------------------------------------------------
    // Outils
    // ------------------------------------------------------------------

    private static function settingExists(PDO $pdo, string $name): bool
    {
        $stmt = $pdo->prepare('SELECT value FROM settings WHERE name = ?');
        $stmt->execute([$name]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Exécute $work dans une transaction, sauf si l'appelant en a déjà ouvert une (tests).
     * Un interblocage (deux requêtes qui créent la même ligne au même instant) est réessayé une fois : la base en a
     * annulé une des deux, qui n'a rien écrit.
     *
     * @template T
     * @param callable():T $work
     * @return T
     */
    private static function atomically(PDO $pdo, callable $work)
    {
        if ($pdo->inTransaction()) {
            return $work();
        }

        for ($attempt = 1; ; $attempt++) {
            $pdo->beginTransaction();
            try {
                $result = $work();
                $pdo->commit();

                return $result;
            } catch (Throwable $e) {
                try {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                } catch (Throwable $ignored) {
                    // la connexion est perdue : l'erreur d'origine est la seule utile
                }

                if ($attempt >= 2 || !self::isDeadlock($e)) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Exécute une requête préparée et retourne le nombre de lignes modifiées.
     * Les entiers sont transmis comme entiers (« occurrences + ? » ne passe pas par un nombre à virgule).
     *
     * @param array<int, int|string|null> $params
     */
    private static function execute(PDO $pdo, string $sql, array $params): int
    {
        $stmt = $pdo->prepare($sql);
        foreach (array_values($params) as $position => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : ($value === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue($position + 1, $value, $type);
        }
        $stmt->execute();

        return $stmt->rowCount();
    }

    /** Interblocage détecté par la base (SQLSTATE 40001, erreur 1213 de MySQL et MariaDB). */
    public static function isDeadlock(Throwable $e): bool
    {
        return $e instanceof PDOException && (string) ($e->errorInfo[0] ?? '') === '40001';
    }

    /** Violation d'une contrainte d'unicité (SQLSTATE 23000, MySQL, MariaDB et SQLite). */
    private static function isDuplicate(PDOException $e): bool
    {
        return (string) $e->getCode() === '23000';
    }

    /** Vrai pour le chemin d'un fichier statique du site (voir STATIC_EXTENSIONS). */
    private static function looksStatic(string $path): bool
    {
        return preg_match('~\.(?:' . self::STATIC_EXTENSIONS . ')$~i', $path) === 1;
    }

    /** Instant au format DATETIME, en UTC. */
    private static function sqlTime(int $timestamp): string
    {
        return gmdate('Y-m-d H:i:s', $timestamp);
    }

    private static function parseSqlTime(string $value): ?int
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new DateTimeZone('UTC'));

        return $date === false ? null : $date->getTimestamp();
    }

    /** Premier instant (UTC) d'un jour au fuseau Europe/Paris. */
    private static function dayStartSql(string $day): string
    {
        return (new DateTimeImmutable($day . ' 00:00:00', new DateTimeZone(VisitPrivacy::TIMEZONE)))
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');
    }

    /**
     * Jour avant lequel les données sont supprimées. Quand le jour n'existe pas (31 mars moins 13 mois), PHP passe au
     * jour suivant : la date limite est un peu plus récente, on garde donc un peu moins que la durée annoncée, jamais plus.
     */
    private static function cutoffDay(string $today, string $interval): string
    {
        return (new DateTimeImmutable($today, new DateTimeZone(VisitPrivacy::TIMEZONE)))
            ->sub(new DateInterval($interval))
            ->format('Y-m-d');
    }
}
