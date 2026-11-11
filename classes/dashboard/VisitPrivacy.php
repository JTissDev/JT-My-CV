<?php
declare(strict_types=1);

/**
 * Fichier : classes/dashboard/VisitPrivacy.php
 * Rôle : Les règles de vie privée de la collecte (docs/dashboard-vie-privee.md, §§ 5 et 6), version de production.
 *
 * Ce sont les fonctions de référence de G1, déplacées ici sans changement de comportement : le dossier tests/
 * n'est pas déployé, le code de la collecte ne peut donc pas en dépendre. tests/dashboard/visit-privacy-reference.php
 * ne contient plus aucune logique : il appelle cette classe, et les 67 contrôles de test_visit_privacy.php
 * portent ainsi sur le code réellement exécuté en production.
 *
 * Aucune dépendance (ni base, ni $_SERVER, ni cookie) : tout est testable seul.
 * Compatible PHP 8.0.
 */
final class VisitPrivacy
{
    /** Écart maximal (en secondes) entre deux pages d'une même visite : au-delà, c'est une nouvelle visite. */
    public const INACTIVITY_SECONDS = 1800;

    /** Fuseau qui définit « le jour » (rotation du sel, effacement des clés). */
    public const TIMEZONE = 'Europe/Paris';

    private function __construct()
    {
    }

    /**
     * Normalise une adresse IP avant hachage. Cette valeur n'est jamais stockée.
     *  - IPv4 : telle quelle
     *  - IPv4 mappée en IPv6 (::ffff:a.b.c.d) : ramenée à l'IPv4
     *  - IPv6 : réduite à son préfixe /64 (les 64 derniers bits changent avec les adresses temporaires)
     * Retourne null si l'adresse est invalide : la visite n'est alors pas suivie.
     */
    public static function ipNormalize(string $ip): ?string
    {
        $packed = @inet_pton(trim($ip));
        if ($packed === false) {
            return null;
        }

        if (strlen($packed) === 4) {
            return inet_ntop($packed);
        }

        if (substr($packed, 0, 12) === "\0\0\0\0\0\0\0\0\0\0\xff\xff") {
            return inet_ntop(substr($packed, 12));
        }

        return inet_ntop(substr($packed, 0, 8) . str_repeat("\0", 8)) . '/64';
    }

    /**
     * Sel du jour : dérivé d'un secret propre au serveur (fichier de config hors Git, jamais en base de données).
     * Une fuite de la base, d'une sauvegarde ou d'un export SQL ne contient donc aucun sel : impossible de retrouver
     * une IP, même par énumération. Le secret doit faire au moins 32 octets.
     *
     * @throws InvalidArgumentException si le secret est trop court (erreur de configuration)
     */
    public static function dailySalt(string $serverSecret, string $day): string
    {
        if (strlen($serverSecret) < 32) {
            throw new InvalidArgumentException('Secret serveur trop court (32 octets minimum).');
        }

        return hash_hmac('sha256', 'visit-salt|' . $day, $serverSecret, true);
    }

    /**
     * Identifiant de visite : HMAC-SHA256(sel du jour, IP normalisée + "|" + User-Agent), tronqué à 128 bits (32 hex).
     * $salt : sel du jour fourni par dailySalt(). Retourne null si l'IP est invalide.
     */
    public static function key(string $salt, string $ip, string $userAgent): ?string
    {
        $normalized = self::ipNormalize($ip);
        if ($normalized === null) {
            return null;
        }

        return substr(hash_hmac('sha256', $normalized . '|' . substr($userAgent, 0, 512), $salt), 0, 32);
    }

    /** Jour (AAAA-MM-JJ, fuseau Europe/Paris) auquel appartient un instant : un sel par jour. */
    public static function day(int $timestamp): string
    {
        return (new DateTimeImmutable('@' . $timestamp))
            ->setTimezone(new DateTimeZone(self::TIMEZONE))
            ->format('Y-m-d');
    }

    /**
     * Vrai si une page appartient à la visite dont la dernière page date de $lastHit (même jour, moins de 30 min).
     * Une visite ne franchit jamais minuit : la clé et le sel changent ce jour-là.
     */
    public static function continues(?int $lastHit, int $now): bool
    {
        return $lastHit !== null
            && $now >= $lastHit
            && ($now - $lastHit) < self::INACTIVITY_SECONDS
            && self::day($lastHit) === self::day($now);
    }

    /**
     * Origine : uniquement le nom de domaine du Referer (jamais le chemin ni la requête, donc jamais de mots-clés).
     * Null = direct, inconnue, navigation interne ou schéma autre que http(s).
     */
    public static function referrerDomain(string $referer, string $ownHost): ?string
    {
        $parts = parse_url(trim($referer));
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        if (!in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower(rtrim($parts['host'], '.'));
        $host = preg_replace('/^www\./', '', $host);
        $own  = preg_replace('/^www\./', '', strtolower($ownHost));

        if ($host === '' || strlen($host) > 253 || preg_match('/^[a-z0-9.-]+$/', $host) !== 1 || $host === $own) {
            return null;
        }

        return $host;
    }

    /**
     * Campagne : seuls utm_source, utm_medium et utm_campaign sont conservés, si leur valeur est sobre.
     *
     * @param array<string, mixed> $query typiquement $_GET
     * @return array<string, string>
     */
    public static function utm(array $query): array
    {
        $kept = [];
        foreach (['utm_source', 'utm_medium', 'utm_campaign'] as $name) {
            $value = $query[$name] ?? null;
            if (is_string($value)) {
                $value = trim($value);
                if (preg_match('/^[A-Za-z0-9._~ -]{1,64}$/', $value) === 1) {
                    $kept[$name] = $value;
                }
            }
        }

        return $kept;
    }
}
