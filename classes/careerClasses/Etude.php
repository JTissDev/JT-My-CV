<?php
declare(strict_types=1);

require_once __DIR__ . '/EvenementParcours.php';

class Etude extends EvenementParcours
{
    private string $etablissement;

    public function __construct(
        string $id,
        string $titre,
        array $description,
        string $dateDebut,
        string $dateFin,
        string $ville = '',
        string $dept = '',
        bool $lienInfo = false,
        array $skillsIds = [],
        ?string $projectId = null,
        string $etablissement = '',
        ?string $linkedGroupId = null,
        ?array $longDescription = null
    ) {
        parent::__construct($id, $titre, $description, $dateDebut, $dateFin, $ville, $dept, $lienInfo, $skillsIds, $projectId, $linkedGroupId, $longDescription);
        $this->etablissement = trim($etablissement);
    }

    public function getEtablissement(): string { return $this->etablissement; }
    public function getType(): string { return 'etude'; }
}
