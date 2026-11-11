<?php
declare(strict_types=1);

/**
 * Référence de G1, désormais sans logique : chaque fonction appelle VisitPrivacy (classes/dashboard/VisitPrivacy.php),
 * le code exécuté en production. Les noms de fonctions et de constantes sont conservés pour que test_visit_privacy.php
 * (67 contrôles) reste inchangé et vérifie le vrai code. Compatible PHP 8.0.
 */

require_once __DIR__ . '/../../classes/dashboard/VisitPrivacy.php';

/** Écart maximal (en secondes) entre deux pages d'une même visite : au-delà, c'est une nouvelle visite. */
const VISIT_INACTIVITY_SECONDS = VisitPrivacy::INACTIVITY_SECONDS;

/** Fuseau qui définit « le jour » (rotation du sel). */
const VISIT_TIMEZONE = VisitPrivacy::TIMEZONE;

function visitIpNormalize(string $ip): ?string
{
    return VisitPrivacy::ipNormalize($ip);
}

function visitDailySalt(string $serverSecret, string $day): string
{
    return VisitPrivacy::dailySalt($serverSecret, $day);
}

function visitKey(string $salt, string $ip, string $userAgent): ?string
{
    return VisitPrivacy::key($salt, $ip, $userAgent);
}

function visitDay(int $timestamp): string
{
    return VisitPrivacy::day($timestamp);
}

function visitContinues(?int $lastHit, int $now): bool
{
    return VisitPrivacy::continues($lastHit, $now);
}

function visitReferrerDomain(string $referer, string $ownHost): ?string
{
    return VisitPrivacy::referrerDomain($referer, $ownHost);
}

/**
 * @param array<string, mixed> $query
 * @return array<string, string>
 */
function visitUtm(array $query): array
{
    return VisitPrivacy::utm($query);
}
