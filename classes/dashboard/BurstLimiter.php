<?php
declare(strict_types=1);

/**
 * Fichier : classes/dashboard/BurstLimiter.php
 * Rôle : Plafonne le nombre d'écritures de la collecte par fenêtre de temps (rafales de 404 d'un scanner).
 *
 * L'hébergement limite la base à 18 connexions simultanées. Un scanner qui demande des centaines d'adresses
 * inexistantes à la seconde ne doit pas monopoliser ces connexions : au-delà du plafond, la collecte ignore
 * la demande SANS ouvrir de connexion, et un compteur garde le nombre de demandes ignorées pour que le
 * dashboard puisse l'afficher (« 412 demandes non détaillées pendant une rafale »).
 *
 * Fonctionnement : une fenêtre fixe, un petit fichier par plafond dans le dossier temporaire, verrouillé le temps de la
 * lecture et de la mise à jour (quelques microsecondes). Aucune base de données, aucune adresse IP, aucun identifiant :
 * le plafond est GLOBAL. Si le dossier temporaire est inutilisable, la collecte n'est pas freinée (le disjoncteur de
 * DashboardDatabase reste la dernière protection) : un frein défaillant ne doit jamais empêcher d'afficher une page.
 *
 * Compatible PHP 8.0.
 */
final class BurstLimiter
{
    private string $directory;

    /** @param string|null $directory dossier des compteurs (par défaut : dossier temporaire du système) */
    public function __construct(?string $directory = null)
    {
        $this->directory = rtrim($directory ?? sys_get_temp_dir(), '/\\');
    }

    /**
     * Compte une demande et dit si elle peut être enregistrée.
     *
     * @param string $name nom du plafond (a-z, 0-9, « _ » et « - », 32 caractères au plus)
     * @param int $limit nombre de demandes acceptées par fenêtre (0 : tout est refusé)
     * @param int $windowSeconds durée de la fenêtre, en secondes (1 au moins)
     * @param int|null $now instant courant (tests)
     * @return bool true : enregistrer ; false : ignorer (la demande est comptée dans « ignorées »)
     * @throws InvalidArgumentException nom, plafond ou durée invalide (erreur de programmation)
     */
    public function allow(string $name, int $limit, int $windowSeconds, ?int $now = null): bool
    {
        if ($limit < 0 || $windowSeconds < 1) {
            throw new InvalidArgumentException('Plafond ou fenêtre invalide.');
        }

        $now = $now ?? time();
        $allowed = true;

        $this->withLock($name, static function (array $state) use ($limit, $windowSeconds, $now, &$allowed): array {
            // Fenêtre écoulée (ou horloge remise en arrière) : on repart de zéro, sans toucher aux « ignorées »
            if ($now < $state['start'] || ($now - $state['start']) >= $windowSeconds) {
                $state['start'] = $now;
                $state['count'] = 0;
            }

            if ($state['count'] < $limit) {
                $state['count']++;
            } else {
                $state['dropped']++;
                $allowed = false;
            }

            return $state;
        });

        return $allowed;
    }

    /**
     * Retourne le nombre de demandes ignorées depuis le dernier appel, et le remet à zéro.
     * À enregistrer par la collecte dès qu'une écriture passe (ex. « rafale : 412 demandes ignorées »).
     */
    public function takeDropped(string $name): int
    {
        $dropped = 0;

        $this->withLock($name, static function (array $state) use (&$dropped): array {
            $dropped = $state['dropped'];
            $state['dropped'] = 0;

            return $state;
        });

        return $dropped;
    }

    /**
     * Lit l'état du compteur sous verrou, le transforme avec $change et le réécrit.
     * Toute défaillance du système de fichiers est ignorée : $change n'est alors pas appelée.
     *
     * @param callable(array{start:int,count:int,dropped:int}):array{start:int,count:int,dropped:int} $change
     */
    private function withLock(string $name, callable $change): void
    {
        if (preg_match('/^[a-z0-9_-]{1,32}$/', $name) !== 1) {
            throw new InvalidArgumentException('Nom de plafond invalide.');
        }

        $path = $this->directory . DIRECTORY_SEPARATOR . 'jt-dashboard-burst-' . substr(sha1(__DIR__), 0, 8) . '-' . $name;

        // On ne suit jamais un lien symbolique (dossier temporaire partagé entre comptes)
        if (is_link($path)) {
            return;
        }

        $isNew = !is_file($path);
        $handle = @fopen($path, 'c+');
        if ($handle === false) {
            return;
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                return;
            }

            if ($isNew) {
                @chmod($path, 0600);
            }

            $raw = stream_get_contents($handle, 128);
            $state = ['start' => 0, 'count' => 0, 'dropped' => 0];
            if (is_string($raw) && preg_match('/^(\d{1,12}) (\d{1,9}) (\d{1,9})$/', trim($raw), $m) === 1) {
                $state = ['start' => (int) $m[1], 'count' => (int) $m[2], 'dropped' => (int) $m[3]];
            }

            $state = $change($state);

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, $state['start'] . ' ' . $state['count'] . ' ' . $state['dropped']);
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }
    }
}
