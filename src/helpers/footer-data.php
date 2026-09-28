<?php
/**
 * Fichier : includes/footer.php
 * Rôle : Pied de page commun à tout le site.
 */

// 1. Sécurisation : on vérifie que le bloc footer existe bien dans le JSON
$footerData = (isset($data) && isset($data->footer)) ? $data->footer : null;

// 2. Récupération des textes avec des valeurs de secours (fallbacks)
$legalNotice   = $footerData ? $footerData->legalNotice : 'Mentions Légales';
$privacyPolicy = $footerData ? $footerData->privacyPolicy : 'Confidentialité';
$terms         = $footerData ? $footerData->terms : 'CGU';
$contact       = $footerData ? $footerData->contact : 'Contact';
$copyrightText = $footerData ? $footerData->copyright : 'Tous droits réservés';

// 3. Génération de l'année en cours dynamiquement via PHP
$currentYear = date('Y');

// 4. Sécurisation de la langue pour les liens
$currentLang = isset($lang) ? $lang : 'fr';