<?php
class CareerManager {
    /**
     * Charge et fusionne le registre et le fichier de langue pour retourner un tableau d'objets.
     */
    public static function getcareerItems(string $langCode = 'fr'): array {
        $registrePath = __DIR__ . '/../data/registre.json';
        $langPath = __DIR__ . "/../data/cv_{$langCode}.json";

        if (!file_exists($registrePath) || !file_exists($langPath)) {
            return [];
        }

        $registre = json_decode(file_get_contents($registrePath));
        $langData = json_decode(file_get_contents($langPath));

        $careerItems = [];

        foreach ($registre->career as $item) {
            $id = $item->id;

            $textData = $langData->career_texts->$id ?? null;
            $titre = $textData->titre ?? 'Titre par défaut';
            $description = $textData->description ?? [];

            $dateDebut = $item->dateDebut ?? '';
            $dateFin = $item->dateFin ?? '';
            $ville = $item->ville ?? '';
            $dept = (string)($item->dept ?? '');
            $lienInfo = $item->lienInfo ?? false;
            $skillsIds = $item->skills_ids ?? [];
            $projectId = $item->projectId ?? null;
            $linkedGroupId = $item->linkedGroupId ?? null; // <-- NOUVEAU : Récupération du groupe de liaison

            switch ($item->category ?? $item->type ?? '') {
                case 'experience':
                    $careerItems[] = new Experience(
                        $id, $titre, $description, 
                        $dateDebut, $dateFin, $ville, $dept, 
                        $lienInfo, $skillsIds, $projectId, 
                        $item->entreprise ?? '',
                        "Professionnel",
                        $linkedGroupId // <-- Transmis ici
                    );
                    break;
                    
                case 'etude':
                    $careerItems[] = new Etude(
                        $id, $titre, $description, 
                        $dateDebut, $dateFin, $ville, $dept, 
                        $lienInfo, $skillsIds, $projectId, 
                        $item->etablissement ?? '',
                        $linkedGroupId // <-- Transmis ici
                    );
                    break;
                    
                case 'projet':
                    $careerItems[] = new ProjetPersonnel(
                        $id, $titre, $description, 
                        $dateDebut, $dateFin, $ville, $dept, 
                        $lienInfo, $skillsIds, $projectId,
                        $linkedGroupId // <-- Transmis ici si ta classe le supporte
                    );
                    break;
            }
        }

        // Tri chronologique (du plus récent au plus ancien)
        usort($careerItems, function($a, $b) {
            return intval($b->getDateDebut()) <=> intval($a->getDateDebut());
        });

        return $careerItems;
    }
}