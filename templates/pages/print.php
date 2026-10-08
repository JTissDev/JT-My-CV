<main class="page-impression">
    <h1><?= htmlspecialchars($data->pages->print->page_title ?? "Défaut : Imprimer mon CV"); ?></h1>

    <div class="controles">
        <label for="profil-selector">
            <?= htmlspecialchars($data->pages->print->selector_label ?? "defaut: Sélectionner un profil"); ?> :
        </label>
        <select id="profil-selector" onchange="changeProfil()">
            <option disabled selected value> -- <?= htmlspecialchars($data->pages->print->selector_label ?? "defaut: Sélectionner un profil"); ?> -- </option>
            <option value="informatique"><?= htmlspecialchars($data->print->profil_title->informatique ?? "default :Développeur & Concepteur Logiciel"); ?></option>
            <option value="commerce"><?= htmlspecialchars($data->print->profil_title->commerce ?? "default :Responsable Commercial & Gestion"); ?></option>
            <option value="industrie"><?= htmlspecialchars($data->print->profil_title->industrie ?? "default :Profil Polyvalent / Technique"); ?></option>

        </select>

        <!-- Le bouton qui lance l'impression -->
        <button onclick="imprimerCV()">🖨️ <?= htmlspecialchars($data->pages->print->print_message ?? "default :Lancer l'impression"); ?></button>
    </div>
    <div class="apercu">
        <iframe id="cv-iframe" src="print/index.php?profil=informatique" width="100%" height="800px" style="border: 1px solid #ccc;"></iframe>
    </div>
</main>