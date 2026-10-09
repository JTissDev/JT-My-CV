<?php
/**
 * Fichier : download_pdf.php
 * Rôle : Génère et force le téléchargement du CV au format PDF en utilisant Dompdf.
 */

// ASTUCE DE DÉBOGAGE : Affiche les erreurs PHP à l'écran au lieu d'une page 500 blanche
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ==========================================
// 1. CORRECTION DU CHEMIN VERS COMPOSER
// ==========================================
// On utilise "/../" pour dire "remonte d'un dossier" depuis le dossier "api".
// Si ton dossier "vendor" est encore plus haut, il faudra peut-être mettre "/../../vendor/autoload.php"
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// 2. Récupérer le profil et la langue
$profilCible = filter_input(INPUT_GET, 'profil', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? 'informatique';
$lang = filter_input(INPUT_GET, 'lang', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? 'fr';

// ==========================================
// 3. CORRECTION DE L'URL DE BASE
// ==========================================
$protocole = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$domaine = $_SERVER['HTTP_HOST'];

// On utilise dirname() DEUX fois pour remonter au-dessus du dossier "api"
// $_SERVER['PHP_SELF'] donne : /JtissDev/app/public/api/download_pdf.php
// dirname 1 donne : /JtissDev/app/public/api
// dirname 2 donne : /JtissDev/app/public
$dossier = dirname(dirname($_SERVER['PHP_SELF'])); 

$baseUrl = $protocole . "://" . $domaine . $dossier; 

// On assemble l'URL finale correctement
$urlDuCV = $baseUrl . "/print/index.php?profil=" . urlencode($profilCible) . "&lang=" . urlencode($lang);

// 4. Récupération du HTML
$html = file_get_contents($urlDuCV);

if ($html === false) {
    die("Erreur : Impossible de récupérer le contenu HTML depuis l'URL : " . $urlDuCV);
}

// 5. Configuration et génération du PDF
$options = new Options();
$options->set('isRemoteEnabled', true); 
$dompdf = new Dompdf($options);

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$nomFichier = "CV_Julien_Tissier_" . ucfirst($profilCible) . ".pdf";
$dompdf->stream($nomFichier, ["Attachment" => 1]);