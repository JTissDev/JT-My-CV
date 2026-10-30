<?php
declare(strict_types=1);

require __DIR__ . '/visit-privacy-reference.php';

$ok = 0;
$ko = 0;
function check(string $label, bool $cond): void
{
    global $ok, $ko;
    if ($cond) { $ok++; } else { $ko++; echo "ÉCHEC : {$label}\n"; }
}

$saltA = random_bytes(32);
$saltB = random_bytes(32);
$ua    = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Test';

// --- Sel du jour dérivé du secret serveur
$secret = random_bytes(32);
$s1 = visitDailySalt($secret, '2026-10-28');
check('sel : 32 octets',                  strlen($s1) === 32);
check('sel : stable pour un jour',        $s1 === visitDailySalt($secret, '2026-10-28'));
check('sel : change chaque jour',         $s1 !== visitDailySalt($secret, '2026-10-29'));
check('sel : dépend du secret',           $s1 !== visitDailySalt(random_bytes(32), '2026-10-28'));
check('sel : jamais le secret lui-même',  $s1 !== $secret);
$tooShort = false;
try { visitDailySalt(str_repeat('a', 31), '2026-10-28'); } catch (InvalidArgumentException $e) { $tooShort = true; }
check('sel : secret trop court refusé',   $tooShort);
check('sel : secret de 32 octets accepté', strlen(visitDailySalt(str_repeat('a', 32), '2026-10-28')) === 32);
check('clé : même IP, deux jours -> deux clés (pas de suivi d\'un jour à l\'autre)',
    visitKey($s1, '203.0.113.7', $ua) !== visitKey(visitDailySalt($secret, '2026-10-29'), '203.0.113.7', $ua));

// --- Normalisation des IP
check('IPv4 inchangée',                 visitIpNormalize('203.0.113.7') === '203.0.113.7');
check('IPv4 mappée -> IPv4',            visitIpNormalize('::ffff:203.0.113.7') === '203.0.113.7');
check('IPv4 mappée (hexa) -> IPv4',     visitIpNormalize('::ffff:cb00:7107') === '203.0.113.7');
check('IPv6 -> /64',                    visitIpNormalize('2001:db8:1:2:aaaa:bbbb:cccc:dddd') === '2001:db8:1:2::/64');
check('IPv6 forme étendue == compacte', visitIpNormalize('2001:0db8:0001:0002:0000:0000:0000:0001') === visitIpNormalize('2001:db8:1:2::1'));
check('IPv6 mêmes 64 bits hauts',       visitIpNormalize('2001:db8:1:2::1') === visitIpNormalize('2001:db8:1:2:ffff:ffff:ffff:ffff'));
check('IPv6 autre /64',                 visitIpNormalize('2001:db8:1:3::1') !== visitIpNormalize('2001:db8:1:2::1'));
check('espaces ignorés',                visitIpNormalize(' 203.0.113.7 ') === '203.0.113.7');
foreach (['', 'abc', '999.1.1.1', '1.2.3', '2001:db8::zz', '1.2.3.4.5', '::ffff:'] as $bad) {
    check("IP invalide « {$bad} »", visitIpNormalize($bad) === null);
}

// --- Identifiant de visite
$k = visitKey($saltA, '203.0.113.7', $ua);
check('32 caractères hexa',             $k !== null && preg_match('/^[0-9a-f]{32}$/', $k) === 1);
check('stable (même sel, IP, UA)',      $k === visitKey($saltA, '203.0.113.7', $ua));
check('change avec le sel',             $k !== visitKey($saltB, '203.0.113.7', $ua));
check('change avec l\'IP',              $k !== visitKey($saltA, '203.0.113.8', $ua));
check('change avec le User-Agent',      $k !== visitKey($saltA, '203.0.113.7', $ua . 'x'));
check('IPv6 temporaires : même clé',    visitKey($saltA, '2001:db8:1:2::1', $ua) === visitKey($saltA, '2001:db8:1:2:1234:5678:9abc:def0', $ua));
check('IPv4 mappée : même clé',         visitKey($saltA, '::ffff:203.0.113.7', $ua) === $k);
check('IP invalide : pas de clé',       visitKey($saltA, 'nimportequoi', $ua) === null);
check('UA tronqué à 512 caractères',    visitKey($saltA, '1.1.1.1', str_repeat('a', 512)) === visitKey($saltA, '1.1.1.1', str_repeat('a', 5000)));
check('UA vide accepté',                visitKey($saltA, '1.1.1.1', '') !== null);

// Pas de collision entre IP différentes sur 100 000 tirages (128 bits) ; un même tirage répété donne la même clé
$seen = [];
$collision = false;
for ($i = 0; $i < 100000; $i++) {
    $ip  = long2ip(random_int(1, 4294967295));
    $key = visitKey($saltA, $ip, $ua);
    if (isset($seen[$key]) && $seen[$key] !== $ip) { $collision = true; break; }
    $seen[$key] = $ip;
}
check('aucune collision entre IP différentes (100 000 tirages)', !$collision);

// Pourquoi le sel doit être détruit : avec le sel, on retrouve l'IP par énumération d'un /16 ; sans lui, non.
$target = visitKey($saltA, '198.51.100.77', $ua);
$found = null;
for ($a = 0; $a < 256 && $found === null; $a++) {
    for ($b = 0; $b < 256; $b++) {
        if (visitKey($saltA, "198.51.{$a}.{$b}", $ua) === $target) { $found = "198.51.{$a}.{$b}"; break; }
    }
}
check('avec le sel : l\'IP est retrouvable (donc sel supprimé le jour même)', $found === '198.51.100.77');
$foundWithoutSalt = false;
for ($a = 0; $a < 256 && !$foundWithoutSalt; $a++) {
    for ($b = 0; $b < 256; $b++) {
        if (visitKey($saltB, "198.51.{$a}.{$b}", $ua) === $target) { $foundWithoutSalt = true; break; }
    }
}
check('avec un autre sel : l\'IP n\'est pas retrouvable', !$foundWithoutSalt);

// --- Jour et continuité (fuseau Europe/Paris, passage à l'heure d'hiver le 25/10/2026)
$t = static fn (string $iso): int => (new DateTimeImmutable($iso))->getTimestamp();
check('jour : 23:59:59 Paris',          visitDay($t('2026-10-28T23:59:59+01:00')) === '2026-10-28');
check('jour : minuit Paris',            visitDay($t('2026-10-29T00:00:00+01:00')) === '2026-10-29');
check('jour : 22:59:59Z = 23:59:59 Paris', visitDay($t('2026-10-28T22:59:59+00:00')) === '2026-10-28');
check('jour : 23:00:00Z = minuit Paris',   visitDay($t('2026-10-28T23:00:00+00:00')) === '2026-10-29');
check('jour : été (UTC+2)',             visitDay($t('2026-07-14T22:30:00+00:00')) === '2026-07-15');

$h = $t('2026-10-28T10:00:00+01:00');
check('visite : première page',              !visitContinues(null, $h));
check('visite : 29 min 59 s -> continue',    visitContinues($h, $h + 1799));
check('visite : 30 min pile -> nouvelle',   !visitContinues($h, $h + 1800));
check('visite : même seconde',                visitContinues($h, $h));
check('visite : horloge à rebours -> nouvelle', !visitContinues($h, $h - 1));
check('visite : ne franchit pas minuit',     !visitContinues($t('2026-10-28T23:50:00+01:00'), $t('2026-10-29T00:05:00+01:00')));

// --- Origine
$own = 'www.jtissier-developpeur-informatique.fr';
check('referer : domaine seul',           visitReferrerDomain('https://www.google.com/search?q=julien+tissier+secret', $own) === 'google.com');
check('referer : chemin et requête jetés',visitReferrerDomain('https://exemple.fr/a/b?token=abc#x', $own) === 'exemple.fr');
check('referer : www retiré, casse',      visitReferrerDomain('HTTPS://WWW.LinkedIn.com/in/x', $own) === 'linkedin.com');
check('referer : navigation interne',     visitReferrerDomain('https://jtissier-developpeur-informatique.fr/mon-parcours/fr', $own) === null);
check('referer : vide',                   visitReferrerDomain('', $own) === null);
check('referer : schéma android-app',     visitReferrerDomain('android-app://com.google.android.gm', $own) === null);
check('referer : javascript:',            visitReferrerDomain('javascript:alert(1)', $own) === null);
check('referer : IPv6 littérale refusée', visitReferrerDomain('http://[::1]/x', $own) === null);
check('referer : caractères douteux',     visitReferrerDomain('http://a_b.example/x', $own) === null);
check('referer : point final retiré',     visitReferrerDomain('https://exemple.fr./x', $own) === 'exemple.fr');
check('referer : identifiants ignorés',   visitReferrerDomain('https://user:pass@exemple.fr/x', $own) === 'exemple.fr');
check('referer : hôte trop long',         visitReferrerDomain('https://' . str_repeat('a', 254) . '.fr/', $own) === null);

// --- Campagne
check('utm : trois champs',               visitUtm(['utm_source' => 'linkedin', 'utm_medium' => 'social', 'utm_campaign' => 'cv-2026']) === ['utm_source' => 'linkedin', 'utm_medium' => 'social', 'utm_campaign' => 'cv-2026']);
check('utm : autres paramètres ignorés',  visitUtm(['page' => 'home', 'utm_source' => 'x', 'email' => 'a@b.fr']) === ['utm_source' => 'x']);
check('utm : tableau refusé',             visitUtm(['utm_source' => ['a']]) === []);
check('utm : caractères interdits',       visitUtm(['utm_source' => '<script>', 'utm_medium' => "a\nb"]) === []);
check('utm : e-mail refusé (@)',          visitUtm(['utm_source' => 'moi@exemple.fr']) === []);
check('utm : 64 caractères max',          visitUtm(['utm_source' => str_repeat('a', 65)]) === [] && visitUtm(['utm_source' => str_repeat('a', 64)]) !== []);
check('utm : espaces rognés',             visitUtm(['utm_source' => '  x  ']) === ['utm_source' => 'x']);
check('utm : vide refusé',                visitUtm(['utm_source' => '   ']) === []);

echo "\nRésultat : {$ok} OK, {$ko} échec(s)\n";
exit($ko === 0 ? 0 : 1);
