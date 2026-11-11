<?php
declare(strict_types=1);

require_once __DIR__ . '/EvenementParcours.php';

class Experience extends EvenementParcours
{
    private string $entreprise;
    private string $typeContrat;

    /** longDescription en dernière position pour des appels nommés ou positionnels sans décalage. */
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
        string $entreprise = '',
        string $typeContrat = 'Professionnel',
        ?string $linkedGroupId = null,
        ?array $longDescription = null
    ) {
        parent::__construct($id, $titre, $description, $dateDebut, $dateFin, $ville, $dept, $lienInfo, $skillsIds, $projectId, $linkedGroupId, $longDescription);
        $this->entreprise = trim($entreprise);
        $this->typeContrat = trim($typeContrat) ?: 'Professionnel';
    }

    public function getEntreprise(): string { return $this->entreprise; }
    public function getTypeContrat(): string { return $this->typeContrat; }
    public function getType(): string { return 'experience'; }
}
