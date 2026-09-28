<?php
class Etude extends EvenementParcours {
    private string $etablissement;

    public function __construct(
        string $id, string $titre, array $description, 
        string $dateDebut, string $dateFin, string $ville, string $dept, 
        bool $lienInfo, array $skillsIds, ?string $projectId, 
        string $etablissement
    ) {
        parent::__construct($id, $titre, $description, $dateDebut, $dateFin, $ville, $dept, $lienInfo, $skillsIds, $projectId);
        $this->etablissement = $etablissement;
    }

    public function getEtablissement(): string { return $this->etablissement; }
}