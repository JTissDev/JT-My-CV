<?php
declare(strict_types=1);

/**
 * Test de classes/dashboard/BurstLimiter.php : plafond d'écritures par fenêtre de temps (rafales de 404).
 * Exécution : php tests/dashboard/test_burst_limiter.php   (ligne de commande uniquement ; aucune base requise)
 *
 * Écrit uniquement dans un dossier temporaire créé pour le test et supprimé à la fin.
 * Le test de concurrence lance 8 processus PHP qui comptent en même temps : le nombre total de demandes acceptées
 * doit être EXACTEMENT le plafond (preuve que le verrou de fichier fonctionne).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../../classes/dashboard/BurstLimiter.php';

$ok = 0;
$ko = 0;
$skipped = 0;
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

function skipSection(string $reason): void
{
    global $skipped;
    $skipped++;
    echo "IGNORÉ : {$reason}\n";
}

/** Supprime un dossier de test (fichiers et liens, un seul niveau). */
function removeDirectory(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) ?: [] as $entry) {
        if ($entry !== '.' && $entry !== '..') {
            @unlink($dir . DIRECTORY_SEPARATOR . $entry);
        }
    }
    @rmdir($dir);
}

// Aucun avertissement PHP ne doit s'échapper du limiteur, même dossier introuvable (les « @ » sont respectés)
$warnings = [];
set_error_handler(static function (int $level, string $message) use (&$warnings): bool {
    if (!(error_reporting() & $level)) {
        return false;
    }
    $warnings[] = $message;

    return true;
});

$dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'jt-burst-test-' . bin2hex(random_bytes(4));
mkdir($dir, 0700);
$isWindows = DIRECTORY_SEPARATOR === '\\';

// --- Plafond et fenêtre
$limiter = new BurstLimiter($dir);
$t = 1_000_000;
$results = [];
for ($i = 0; $i < 5; $i++) {
    $results[] = $limiter->allow('a', 3, 60, $t);
}
check('plafond de 3 : trois acceptées puis refusées', $results === [true, true, true, false, false]);
check('deux demandes ignorées comptées',              $limiter->takeDropped('a') === 2);
check('compteur remis à zéro après lecture',          $limiter->takeDropped('a') === 0);
check('toujours refusé dans la fenêtre (t+59)',       $limiter->allow('a', 3, 60, $t + 59) === false);
check('accepté à la fenêtre suivante (t+60)',         $limiter->allow('a', 3, 60, $t + 60) === true);
check('3 acceptées dans la nouvelle fenêtre',         $limiter->allow('a', 3, 60, $t + 61) === true && $limiter->allow('a', 3, 60, $t + 62) === true && $limiter->allow('a', 3, 60, $t + 63) === false);
check('ignorées conservées jusqu\'à lecture',          $limiter->takeDropped('a') === 2);

$fresh = new BurstLimiter($dir);
check('une seule demande par fenêtre : limite 1',      $fresh->allow('b', 1, 10, 500) === true && $fresh->allow('b', 1, 10, 501) === false && $fresh->allow('b', 1, 10, 510) === true);
check('plafond 0 : tout est refusé et compté',         $fresh->allow('z', 0, 60, 1) === false && $fresh->allow('z', 0, 60, 2) === false && $fresh->takeDropped('z') === 2);
check('horloge remise en arrière : nouvelle fenêtre',  $fresh->allow('c', 1, 60, 9000) === true && $fresh->allow('c', 1, 60, 9001) === false && $fresh->allow('c', 1, 60, 100) === true);
check('ignorées survivent au changement de fenêtre',   (static function (BurstLimiter $l): bool {
    $l->allow('d', 1, 5, 0);
    $l->allow('d', 1, 5, 1);
    $l->allow('d', 1, 5, 2);
    $l->allow('d', 1, 5, 100);

    return $l->takeDropped('d') === 2;
})($fresh));

// --- Les plafonds sont indépendants, et un autre objet voit le même état (c'est le fichier qui porte le compteur)
$one = new BurstLimiter($dir);
$two = new BurstLimiter($dir);
$one->allow('e', 2, 60, 7000);
$two->allow('e', 2, 60, 7000);
check('état partagé entre deux objets (le fichier fait foi)', $one->allow('e', 2, 60, 7000) === false);
check('un autre nom a son propre compteur',                  $one->allow('f', 2, 60, 7000) === true);
check('compteurs d\'ignorées séparés par nom',               $one->takeDropped('f') === 0 && $one->takeDropped('e') === 1);

// --- Paramètres invalides : erreur de programmation signalée
$threw = static function (callable $call): bool {
    try {
        $call();
    } catch (InvalidArgumentException $e) {
        return true;
    }

    return false;
};
check('nom vide refusé',                  $threw(static fn () => $limiter->allow('', 1, 1)));
check('nom avec « / » refusé',            $threw(static fn () => $limiter->allow('../x', 1, 1)));
check('nom en majuscules refusé',         $threw(static fn () => $limiter->allow('A', 1, 1)));
check('nom de 33 caractères refusé',      $threw(static fn () => $limiter->allow(str_repeat('a', 33), 1, 1)));
check('nom de 32 caractères accepté',     !$threw(static fn () => $limiter->allow(str_repeat('a', 32), 1, 1)));
check('plafond négatif refusé',           $threw(static fn () => $limiter->allow('g', -1, 1)));
check('fenêtre de 0 seconde refusée',     $threw(static fn () => $limiter->allow('g', 1, 0)));
check('nom invalide refusé aussi à la lecture', $threw(static fn () => $limiter->takeDropped('../x')));

// --- Fichier de compteur abîmé : on repart de zéro, sans avertissement
$probe = new BurstLimiter($dir);
$probe->allow('h', 5, 60, 100);
$files = glob($dir . DIRECTORY_SEPARATOR . 'jt-dashboard-burst-*-h') ?: [];
check('un fichier de compteur par nom, dans le dossier demandé', count($files) === 1);
file_put_contents($files[0], "n'importe quoi\x00\xFF");
check('fichier illisible : repart de zéro et accepte', $probe->allow('h', 1, 60, 200) === true && $probe->allow('h', 1, 60, 201) === false);
file_put_contents($files[0], '');
check('fichier vide : repart de zéro',                 $probe->allow('h', 1, 60, 300) === true);
file_put_contents($files[0], '99999999999999999999 1 1');
check('nombres démesurés : repart de zéro',            $probe->allow('h', 1, 60, 400) === true);
check('contenu du compteur : trois entiers',           preg_match('/^\d+ \d+ \d+$/', (string) file_get_contents($files[0])) === 1);
if ($isWindows) {
    skipSection('droits 0600 non contrôlés sous Windows');
} else {
    check('fichier de compteur en 0600 (lecture réservée au propriétaire)', (fileperms($files[0]) & 0777) === 0600);
}

// --- Dossier inutilisable : la collecte n'est pas freinée (échec silencieux)
$missing = new BurstLimiter($dir . DIRECTORY_SEPARATOR . 'absent' . DIRECTORY_SEPARATOR . 'sous-dossier');
check('dossier introuvable : demande acceptée',        $missing->allow('x', 1, 60, 1) === true && $missing->allow('x', 1, 60, 2) === true);
check('dossier introuvable : aucune ignorée',          $missing->takeDropped('x') === 0);

// --- Lien symbolique : jamais suivi
if ($isWindows) {
    skipSection('lien symbolique non contrôlé sous Windows');
} else {
    $class = new ReflectionClass(BurstLimiter::class);
    $path = $dir . DIRECTORY_SEPARATOR . 'jt-dashboard-burst-' . substr(sha1(dirname((string) $class->getFileName())), 0, 8) . '-lien';
    $target = $dir . DIRECTORY_SEPARATOR . 'cible-sensible';
    file_put_contents($target, 'NE PAS TOUCHER');
    if (@symlink($target, $path)) {
        check('lien symbolique : demande acceptée sans rien écrire', (new BurstLimiter($dir))->allow('lien', 1, 60, 1) === true && (new BurstLimiter($dir))->allow('lien', 1, 60, 2) === true);
        check('lien symbolique : la cible n\'est pas modifiée',      file_get_contents($target) === 'NE PAS TOUCHER');
    } else {
        skipSection('création de lien symbolique impossible');
    }
}

// --- Concurrence : 8 processus comptent en même temps, le total accepté est exactement le plafond
if (!function_exists('proc_open')) {
    skipSection('proc_open indisponible : concurrence non contrôlée');
} else {
    $child = $dir . DIRECTORY_SEPARATOR . 'enfant.php';
    file_put_contents($child, <<<'PHP'
<?php
require $argv[1];
$limiter = new BurstLimiter($argv[2]);
$accepted = 0;
for ($i = 0; $i < 50; $i++) {
    if ($limiter->allow('course', 100, 3600, 5000)) {
        $accepted++;
    }
}
echo $accepted;
PHP);

    $classFile = (string) (new ReflectionClass(BurstLimiter::class))->getFileName();
    $processes = [];
    for ($n = 0; $n < 8; $n++) {
        $process = proc_open([PHP_BINARY, $child, $classFile, $dir], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (is_resource($process)) {
            $processes[] = [$process, $pipes];
        }
    }

    $total = 0;
    $finished = 0;
    foreach ($processes as [$process, $pipes]) {
        $out = (string) stream_get_contents($pipes[1]);
        $err = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        if (preg_match('/^\d+$/', $out) === 1 && $err === '') {
            $total += (int) $out;
            $finished++;
        }
    }

    check('8 processus lancés et terminés sans erreur',                $finished === 8);
    check('400 demandes simultanées, plafond 100 : exactement 100 acceptées', $total === 100);
    check('300 demandes ignorées, comptées sans perte',                (new BurstLimiter($dir))->takeDropped('course') === 300);
}

check('aucun avertissement PHP pendant tout le test', $warnings === []);

restore_error_handler();
removeDirectory($dir);
check('dossier de test supprimé', !is_dir($dir));

// --- Le code de production ne lit ni cookie ni session
$source = (string) file_get_contents(__DIR__ . '/../../classes/dashboard/BurstLimiter.php');
check('BurstLimiter : aucune lecture de cookie ni de session', preg_match('/\$_COOKIE|\$_SESSION|setcookie|session_start|set-cookie/i', $source) !== 1);
check('BurstLimiter : aucune adresse IP lue',                  preg_match('/REMOTE_ADDR|X_FORWARDED|HTTP_CLIENT_IP/i', $source) !== 1);

echo "\nRésultat : {$ok} OK, {$ko} échec(s), {$skipped} section(s) ignorée(s)\n";
exit($ko === 0 ? 0 : 1);
