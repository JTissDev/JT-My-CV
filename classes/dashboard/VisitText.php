<?php
declare(strict_types=1);

/**
 * Fichier : classes/dashboard/VisitText.php
 * Rôle : Nettoie tout texte venu du visiteur avant son écriture en base (User-Agent d'un robot, chemin d'une 404).
 *
 * La base tourne en mode strict : une valeur qui n'est pas de l'UTF-8 valide, ou plus longue que la colonne,
 * est une erreur et non une troncature silencieuse. Un scanner qui envoie des octets quelconques ne doit donc jamais
 * pouvoir faire échouer l'écriture (ni, a fortiori, la page). Règles appliquées, dans l'ordre :
 *   1. entrée bornée à 4096 octets (avant tout traitement coûteux) ;
 *   2. UTF-8 invalide : tous les octets non ASCII sont remplacés par « ? » ;
 *   3. caractères de contrôle, de formatage (dont les inverseurs de sens d'écriture) et séparateurs de ligne retirés ;
 *   4. longueur bornée en CARACTÈRES (les colonnes VARCHAR comptent des caractères), sans couper un caractère.
 *
 * Compatible PHP 8.0, sans dépendance à mbstring.
 */
final class VisitText
{
    /** Longueur maximale des colonnes de texte libre (errors.subject, visits.bot_agent). */
    public const MAX_LENGTH = 255;

    /** Octets conservés avant traitement : 4 octets par caractère au plus, plus une marge. */
    private const MAX_INPUT_BYTES = 4096;

    private function __construct()
    {
    }

    /** Texte sûr pour une colonne VARCHAR($max) : voir les règles en tête de fichier. */
    public static function clean(string $text, int $max = self::MAX_LENGTH): string
    {
        if ($max < 1) {
            return '';
        }

        if (strlen($text) > self::MAX_INPUT_BYTES) {
            $text = substr($text, 0, self::MAX_INPUT_BYTES);
            // La coupe a pu tomber au milieu d'un caractère : on retire la séquence incomplète de la fin
            $text = (string) preg_replace('/[\xC0-\xFF][\x80-\xBF]{0,2}\z/', '', $text);
        }

        if (preg_match('//u', $text) !== 1) {
            $text = (string) preg_replace('/[\x80-\xFF]/', '?', $text);
        }

        $text = (string) preg_replace('/[\p{Cc}\p{Cf}\p{Zl}\p{Zp}]+/u', '', $text);

        if (preg_match('/^.{0,' . $max . '}/su', $text, $match) === 1) {
            $text = $match[0];
        }

        return trim($text);
    }

    /**
     * Chemin demandé, sans la requête ni le fragment : « /a/b?x=1#y » devient « /a/b ».
     * Le résultat commence toujours par « / » (« / » seul si rien ne reste). Le jeton d'un lien de visite guidée,
     * placé dans la requête, n'arrive donc jamais en base.
     */
    public static function path(string $requestUri, int $max = self::MAX_LENGTH): string
    {
        $cut = strcspn($requestUri, '?#');
        $path = self::clean(substr($requestUri, 0, $cut), $max);

        if ($path === '' || $path[0] !== '/') {
            $path = '/' . $path;
            $path = self::clean($path, $max);
        }

        return $path;
    }

    /** User-Agent d'un robot ou du robot de test, borné à 255 caractères (jamais utilisé pour un humain). */
    public static function userAgent(string $userAgent): string
    {
        return self::clean($userAgent, self::MAX_LENGTH);
    }
}
