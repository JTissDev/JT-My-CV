<main class="page-impression">
    <h1><?= htmlspecialchars($data->pages->print->page_title ?? "Imprimer mon CV"); ?></h1>

    <div class="controles">
        <label for="profil-selector">
            <?= htmlspecialchars($data->pages->print->selector_label ?? "Sélectionner un profil"); ?> :
        </label>
        <select id="profil-selector" onchange="changeProfil()">
            <option disabled selected value> -- <?= htmlspecialchars($data->pages->print->selector_label ?? "Sélectionner un profil"); ?> -- </option>
            <option value="informatique"><?= htmlspecialchars($data->print->titres_profil->informatique ?? "Développeur & Concepteur Logiciel"); ?></option>
            <option value="commerce"><?= htmlspecialchars($data->print->titres_profil->commerce ?? "Responsable Commercial & Gestion"); ?></option>
            <option value="industrie"><?= htmlspecialchars($data->print->titres_profil->industrie ?? "Profil Polyvalent / Technique"); ?></option>

        </select>

        <!-- Le bouton qui lance l'impression -->
        <button onclick="imprimerCV()">🖨️ <?= htmlspecialchars($data->pages->print->print_message ?? "Lancer l'impression"); ?></button>
    </div>
    <div class="apercu">
        <iframe id="cv-iframe" src="paper/index.php?profil=informatique" width="100%" height="800px" style="border: 1px solid #ccc;"></iframe>
    </div>
</main>