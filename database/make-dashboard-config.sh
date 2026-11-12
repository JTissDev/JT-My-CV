#!/usr/bin/env bash
# make-dashboard-config.sh — Crée le dashboard.config.php de PRODUCTION, hors du dépôt Git.
#
# Le mot de passe de la base est saisi ici, sans affichage : il ne passe ni par une conversation, ni par l'historique du
# terminal, ni par Git. Le secret serveur (32 octets aléatoires) est généré ici et n'est jamais affiché.
#
# Usage (depuis n'importe quel dossier) :
#   ./database/make-dashboard-config.sh [dossier de sortie]
#       dossier de sortie par défaut : ~/dashboard-prod
#       Il doit être HORS du projet et hors de tout dépôt Git : le script refuse sinon.
# Résultat : <dossier>/dashboard.config.php, à déposer par SFTP dans cv-web/, à côté de index.php
#            (procédure complète : docs/dashboard-mise-en-ligne.md).
#
# Valeurs demandées, à recopier depuis l'espace client IONOS (Hébergement > Bases de données) :
#   serveur, port, nom de la base, utilisateur, mot de passe.
# Le fichier créé est contrôlé par la vraie classe DashboardConfig du projet avant la fin du script.
# Il ne remplace jamais un fichier existant, et ne touche jamais au dashboard.config.php du projet (poste local).
set -u
set -o pipefail
umask 077
# Octets bruts : longueurs et plages de caractères identiques à celles de PHP (strlen) quel que soit le terminal
export LC_ALL=C

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
OUT_DIR="${1:-${HOME}/dashboard-prod}"
FILE_NAME="dashboard.config.php"

die() { echo "ERREUR : $*" >&2; exit 1; }

[ $# -le 1 ] || die "un seul argument attendu : le dossier de sortie."
[ -f "${ROOT}/classes/dashboard/DashboardConfig.php" ] || die "classes/dashboard/DashboardConfig.php introuvable : lance ce script depuis le projet (database/make-dashboard-config.sh)."
command -v php > /dev/null 2>&1 || die "php n'est pas dans le PATH (Laragon : ouvre le terminal Laragon, ou ajoute php au PATH)."

# ---- Dossier de sortie : hors du projet et hors de tout dépôt Git -----------------------------------------------------
CREATED_DIR=0
if [ ! -d "${OUT_DIR}" ]; then
  mkdir -p "${OUT_DIR}" || die "impossible de créer ${OUT_DIR}."
  CREATED_DIR=1
fi
OUT_DIR="$(cd "${OUT_DIR}" && pwd -P)"
TARGET="${OUT_DIR}/${FILE_NAME}"

refuse_dir() {
  [ "${CREATED_DIR}" -eq 1 ] && rmdir "${OUT_DIR}" 2> /dev/null
  die "$1"
}
case "${OUT_DIR}/" in
  "${ROOT}/"*) refuse_dir "le dossier de sortie (${OUT_DIR}) est dans le projet : choisis un dossier ailleurs, par exemple ~/dashboard-prod." ;;
esac
if command -v git > /dev/null 2>&1 && [ "$(git -C "${OUT_DIR}" rev-parse --is-inside-work-tree 2> /dev/null)" = "true" ]; then
  refuse_dir "le dossier de sortie (${OUT_DIR}) est dans un dépôt Git : choisis un dossier ailleurs."
fi
[ ! -e "${TARGET}" ] || die "${TARGET} existe déjà : il n'est jamais remplacé. Déplace-le ou choisis un autre dossier."

# ---- Questions ------------------------------------------------------------------------------------------------------
# ask <variable> <libellé> <expression régulière> [valeur par défaut] : 3 essais, réponse contrôlée comme le fait DashboardConfig
ask() {
  local var="$1" label="$2" pattern="$3" default="${4:-}" try answer
  for try in 1 2 3; do
    printf '%s : ' "${label}" >&2
    IFS= read -r answer || die "saisie interrompue."
    if [ -z "${answer}" ]; then answer="${default}"; fi
    if [[ "${answer}" =~ ${pattern} ]]; then
      printf -v "${var}" '%s' "${answer}"
      return 0
    fi
    echo "  valeur invalide, recommence (${try}/3)." >&2
  done
  die "trois valeurs invalides pour « ${label} »."
}

echo "Création de ${TARGET}" >&2
echo "(recopie les valeurs depuis l'espace client IONOS ; rien de ce que tu tapes pour le mot de passe n'est affiché)" >&2
ask HOST "Serveur de la base (ex. dbNNNN.hosting-data.io)" '^[A-Za-z0-9]([A-Za-z0-9._-]{0,251}[A-Za-z0-9])?$'
ask PORT "Port (Entrée = 3306)" '^[0-9]{1,5}$' 3306
{ [ "${PORT}" -ge 1 ] && [ "${PORT}" -le 65535 ]; } 2> /dev/null || die "le port doit être compris entre 1 et 65535."
PORT=$((10#${PORT}))
ask NAME "Nom de la base (ex. dbsNNNN)" '^[A-Za-z0-9_]{1,64}$'
ask USER_NAME "Utilisateur de la base (ex. dbuNNNN)" '^[!-~]{1,80}$'

PASSWORD=""
for try in 1 2 3; do
  printf 'Mot de passe de la base (non affiché) : ' >&2
  IFS= read -rs PASSWORD || die "saisie interrompue."
  echo >&2
  printf 'Confirme le mot de passe : ' >&2
  IFS= read -rs CONFIRM || die "saisie interrompue."
  echo >&2
  if [ -z "${PASSWORD}" ]; then
    echo "  le mot de passe est vide, recommence (${try}/3)." >&2
  elif [ "${PASSWORD}" != "${CONFIRM}" ]; then
    echo "  les deux saisies sont différentes, recommence (${try}/3)." >&2
  elif [ "${#PASSWORD}" -gt 256 ] || grep -q '[^ -~]' <<< "${PASSWORD}"; then
    echo "  le mot de passe doit être en caractères ASCII imprimables (256 au plus) : change-le dans l'espace client IONOS si besoin." >&2
  else
    break
  fi
  PASSWORD=""
done
[ -n "${PASSWORD}" ] || die "mot de passe non valide ou non confirmé."
unset CONFIRM

# ---- Secret serveur : 32 octets aléatoires (64 caractères hexadécimaux), jamais affiché ------------------------------
SECRET="$(php -r 'echo bin2hex(random_bytes(32));')" || die "impossible de générer le secret."
[[ "${SECRET}" =~ ^[0-9a-f]{64}$ ]] || die "le secret généré est inattendu : relance le script."

# ---- Écriture : les valeurs passent par l'environnement, jamais par la ligne de commande ----------------------------
DASH_HOST="${HOST}" DASH_PORT="${PORT}" DASH_NAME="${NAME}" DASH_USER="${USER_NAME}" \
DASH_PASSWORD="${PASSWORD}" DASH_SECRET="${SECRET}" DASH_TARGET="${TARGET}" \
php <<'PHP' || die "écriture du fichier impossible (le fichier existe-t-il déjà ?)."
<?php
$values = [];
foreach (['host' => 'DASH_HOST', 'port' => 'DASH_PORT', 'name' => 'DASH_NAME', 'user' => 'DASH_USER',
          'password' => 'DASH_PASSWORD', 'secret' => 'DASH_SECRET'] as $key => $variable) {
    $value = getenv($variable);
    if ($value === false) {
        fwrite(STDERR, "variable $variable absente\n");
        exit(1);
    }
    $values[$key] = $value;
}
$quote = static function (string $text): string {
    return var_export($text, true);
};
$code = "<?php\n"
    . "// Configuration du dashboard : PRODUCTION. Créée le " . date('d/m/Y à H:i') . " par database/make-dashboard-config.sh.\n"
    . "// Ne jamais mettre ce fichier dans Git ni l'envoyer dans une conversation.\n"
    . "return [\n"
    . "    'db_host'     => " . $quote($values['host']) . ",\n"
    . "    'db_port'     => " . (int) $values['port'] . ",\n"
    . "    'db_name'     => " . $quote($values['name']) . ",\n"
    . "    'db_user'     => " . $quote($values['user']) . ",\n"
    . "    'db_password' => " . $quote($values['password']) . ",\n"
    . "    'secret'      => " . $quote($values['secret']) . ",\n"
    . "];\n";
// Mode « x » : ne remplace jamais un fichier qui existerait déjà
$handle = @fopen((string) getenv('DASH_TARGET'), 'xb');
if ($handle === false) {
    fwrite(STDERR, "création impossible (le fichier existe déjà, ou le dossier n'est pas accessible en écriture)\n");
    exit(1);
}
if (fwrite($handle, $code) !== strlen($code) || !fclose($handle)) {
    @unlink((string) getenv('DASH_TARGET'));
    fwrite(STDERR, "écriture incomplète : fichier supprimé\n");
    exit(1);
}
PHP
chmod 600 "${TARGET}" 2> /dev/null || true

# ---- Contrôle : la vraie classe du projet relit le fichier, et retrouve exactement ce qui a été saisi ---------------
DASH_ROOT="${ROOT}" DASH_TARGET="${TARGET}" DASH_HOST="${HOST}" DASH_PORT="${PORT}" DASH_NAME="${NAME}" \
DASH_USER="${USER_NAME}" DASH_PASSWORD="${PASSWORD}" DASH_SECRET="${SECRET}" \
php <<'PHP' || { rm -f "${TARGET}"; die "le fichier créé a été supprimé : relance le script."; }
<?php
require_once getenv('DASH_ROOT') . '/classes/dashboard/DashboardConfig.php';
try {
    $config = DashboardConfig::load((string) getenv('DASH_TARGET'));
} catch (Throwable $e) {
    fwrite(STDERR, 'Fichier refusé par DashboardConfig : ' . $e->getMessage() . "\n");
    exit(1);
}
$expected = [
    'db_host'       => getenv('DASH_HOST'),
    'db_port'       => (int) getenv('DASH_PORT'),
    'db_name'       => getenv('DASH_NAME'),
    'db_user'       => getenv('DASH_USER'),
    'db_password'   => getenv('DASH_PASSWORD'),
    'server_secret' => hex2bin((string) getenv('DASH_SECRET')),
];
foreach ($expected as $key => $value) {
    if ($config[$key] !== $value) {
        fwrite(STDERR, "La valeur relue pour « $key » ne correspond pas à la saisie.\n");
        exit(1);
    }
}
PHP

unset PASSWORD SECRET
echo
echo "OK : ${TARGET} créé et relu avec succès par DashboardConfig."
echo "     Serveur ${HOST}:${PORT}, base ${NAME}, utilisateur ${USER_NAME}."
echo "     Mot de passe et secret (64 caractères hexadécimaux) : écrits dans le fichier, jamais affichés."
echo
echo "Suite : dépose ce fichier par SFTP dans cv-web/ (à côté de index.php), puis suis docs/dashboard-mise-en-ligne.md."
echo "Ne le copie ni dans le projet, ni dans Git, ni dans une conversation."
