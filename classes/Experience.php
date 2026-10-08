<?php
class Experience extends EvenementParcours {
    private string $entreprise;
    private string $typeContrat;

    public function __construct(
        string $id, string $titre, array $description, 
        string $dateDebut, string $dateFin, string $ville, string $dept, 
        bool $lienInfo, array $skillsIds, ?string $projectId, 
        string $entreprise, string $typeContrat = "Professionnel",
        ?string $linkedGroupId = null // <-- NOUVEAU
    ) {
        parent::__construct(
            $id, $titre, $description, 
            $dateDebut, $dateFin, $ville, $dept, 
            $lienInfo, $skillsIds, $projectId, 
            $linkedGroupId // Transmission à la classe parente
        );
        $this->entreprise = $entreprise;
        $this->typeContrat = $typeContrat;
    }

    public function getEntreprise(): string { return $this->entreprise; }
    public function getTypeContrat(): string { return $this->typeContrat; }
}