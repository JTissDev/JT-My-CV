<?php
class ProjetPersonnel extends EvenementParcours {
    public function __construct(
        string $id, string $titre, array $description, 
        string $dateDebut, string $dateFin, string $ville, string $dept, 
        bool $lienInfo, array $skillsIds, ?string $projectId
    ) {
        parent::__construct($id, $titre, $description, $dateDebut, $dateFin, $ville, $dept, $lienInfo, $skillsIds, $projectId);
    }
}