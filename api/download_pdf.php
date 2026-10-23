<?php

/**
 * Fichier : api/download_pdf.php
 * Rôle : Export PDF (Dompdf) du CV imprimable : rend print/index.php puis envoie le PDF.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/helpers/print-options.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Paramètres validés par liste blanche : toute autre valeur retombe sur la valeur par défaut.
$lang   = resolvePrintOption($_GET['lang'] ?? null, PRINT_ALLOWED_LANGS);
$profil = resolvePrintOption($_GET['profil'] ?? null, PRINT_ALLOWED_PROFILES);

// print/index.php ne doit recevoir que ces deux valeurs (aucun autre paramètre, ex. "template").
$_GET = ['profil' => $profil, 'lang' => $lang];

$options = new Options();
// Rien n'est chargé depuis Internet : feuilles de style et images sont locales.
$options->set('isRemoteEnabled', false);

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
require __DIR__ . '/../print/index.php';
$html = ob_get_clean();

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$dompdf->stream("CV_Julien_Tissier_{$profil}.pdf", ['Attachment' => true]);
exit;