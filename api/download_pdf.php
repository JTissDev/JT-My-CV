<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$lang   = filter_input(INPUT_GET, 'lang', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? 'fr';
$profil = filter_input(INPUT_GET, 'profil', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? 'informatique';

$options = new Options();
$options->set('isRemoteEnabled', true);

// Autoriser Dompdf à lire les fichiers locaux du projet
$rootPath = realpath(__DIR__ . '/../');
if ($rootPath !== false) {
    $options->setChroot($rootPath);
}

$dompdf = new Dompdf($options);

// Définir le chemin de base pour que Dompdf trouve le dossier styles/
$printPath = realpath(__DIR__ . '/../print/');
if ($printPath !== false) {
    $dompdf->setBasePath($printPath);
}

ob_start();
$_GET['profil'] = $profil;
$_GET['lang']   = $lang;
require __DIR__ . '/../print/index.php';
$html = ob_get_clean();

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$dompdf->stream("CV_Julien_Tissier_{$profil}.pdf", ["Attachment" => true]);
exit;