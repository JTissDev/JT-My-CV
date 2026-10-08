// scripts/print.js

function changeProfil() {
    const profil = document.getElementById('profil-selector').value;
    const iframe = document.getElementById('cv-iframe');
    
    // Si l'utilisateur a bien sélectionné une valeur (et non le label désactivé par défaut)
    if (profil) {
        // Met à jour la source de l'iframe pour appeler le script PHP d'impression
        iframe.src = `paper/index.php?profil=${profil}`;
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