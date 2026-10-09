// scripts/print.js

function changeProfil() {
    const profil = document.getElementById('profil-selector').value;
    const iframe = document.getElementById('cv-iframe');
    
    // Si l'utilisateur a bien sélectionné une valeur (et non le label désactivé par défaut)
    if (profil) {
        // Met à jour la source de l'iframe pour appeler le script PHP d'impression
        iframe.src = `print/index.php?profil=${profil}`;
    }
}

function imprimerCV() {
    const iframe = document.getElementById('cv-iframe');
    
    // Vérifie si l'iframe a bien chargé une page avant de lancer l'impression
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
        // Redirige vers le contrôleur PHP avec le profil ET la langue
        window.location.href = `api/download_pdf.php?profil=${profil}&lang=${langueActuelle}`;
    } else {
        alert("Veuillez d'abord sélectionner un profil à télécharger.");
    }
}