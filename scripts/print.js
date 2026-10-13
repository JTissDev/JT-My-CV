// scripts/print.js

/**
 * Modifie le profil affiché dans l'iframe de prévisualisation en conservant la langue active.
 * @param {string} profileName - Le nom du profil à charger.
 */
function changeProfil() {
    const profil = document.getElementById('profil-selector').value;
    const iframe = document.getElementById('cv-iframe');
    
    // Si l'utilisateur a bien sélectionné une valeur et que l'iframe existe
    if (profil && iframe) {
        // Récupération de la langue active depuis l'URL de la page parente
        const urlParams = new URLSearchParams(window.location.search);
        const currentLang = urlParams.get('lang') || 'fr';
        
        // Met à jour la source de l'iframe avec le profil et la langue active
        iframe.src = `print/index.php?profil=${encodeURIComponent(profil)}&lang=${encodeURIComponent(currentLang)}`;
    }
}

function imprimerCV() {
    const iframe = document.getElementById('cv-iframe');
    
    if (iframe && iframe.src) {
        iframe.contentWindow.focus();
        iframe.contentWindow.print();
    } else {
        alert("Veuillez d'abord sélectionner un profil à imprimer.");
    }
}

// NOUVEAU : La fonction accepte désormais la langue en paramètre
function telechargerPDF(langueActuelle) {
    const profil = document.getElementById('profil-selector').value;
    
    if (profil) {
        window.location.href = `api/download_pdf.php?profil=${profil}&lang=${langueActuelle}`;
    } else {
        alert("Veuillez d'abord sélectionner un profil à télécharger.");
    }
}