<?php
class ParcoursManager {
    /**
     * Charge et fusionne le registre et le fichier de langue pour retourner un tableau d'objets.
     */
    public static function getParcoursItems(string $langCode = 'fr'): array {
        // Chemins vers tes fichiers JSON
        $registrePath = __DIR__ . '/../data/registre.json';
        $langPath = __DIR__ . "/../data/cv_{$langCode}.json";

        // Vérification de l'existence des fichiers
        if (!file_exists($registrePath) || !file_exists($langPath)) {
            return [];
        }

        $registre = json_decode(file_get_contents($registrePath));
        $langData = json_decode(file_get_contents($langPath));

        $parcoursItems = [];

        foreach ($registre->parcours as $item) {
            $id = $item->id;

            // Récupération sécurisée du texte traduit via l'ID
            $textData = $langData->parcours_texts->$id ?? null;
            $titre = $textData->titre ?? 'Titre par défaut';
            $description = $textData->description ?? [];

            // Sécurisation des propriétés optionnelles ou absentes du JSON
            $dateDebut = $item->dateDebut ?? '';
            $dateFin = $item->dateFin ?? '';
            $ville = $item->ville ?? '';
            $dept = $item->dept ?? ''; // <-- Sécurisé ici pour éviter le null
            $lienInfo = $item->lienInfo ?? false;
            $skillsIds = $item->skills_ids ?? [];
            $projectId = $item->projectId ?? null;

            // Instanciation de l'objet selon son type
            switch ($item->type) {
                case 'experience':
                    $parcoursItems[] = new Experience(
                        $id, $titre, $description, 
                        $dateDebut, $dateFin, $ville, $dept, 
                        $lienInfo, $skillsIds, $projectId, 
                        $item->entreprise ?? '' // Correction de la coquille précédente ici aussi
                    );
                    break;
                    
                case 'etude':
                    $parcoursItems[] = new Etude(
                        $id, $titre, $description, 
                        $dateDebut, $dateFin, $ville, $dept, 
                        $lienInfo, $skillsIds, $projectId, 
                        $item->etablissement ?? ''
                    );
                    break;
                    
                case 'projet':
                    $parcoursItems[] = new ProjetPersonnel(
                        $id, $titre, $description, 
                        $dateDebut, $dateFin, $ville, $dept, 
                        $lienInfo, $skillsIds, $projectId
                    );
                    break;
            }
        }

        // --- TRI CHRONOLOGIQUE (Du plus récent au plus ancien) ---
        usort($parcoursItems, function($a, $b) {
            // On compare les dates de début en convertissant en entier (ex: 2025 vs 2024)
            return intval($b->getDateDebut()) <=> intval($a->getDateDebut());
        });
        return $parcoursItems;
    }
}