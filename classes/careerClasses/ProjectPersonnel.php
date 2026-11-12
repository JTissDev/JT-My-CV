<?php
declare(strict_types=1);

require_once __DIR__ . '/EvenementParcours.php';

/** Nom de classe calé sur ProjectPersonnel.php et la clé category=projet du registre. */
class ProjectPersonnel extends EvenementParcours
{
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
        ?string $linkedGroupId = null,
        ?array $longDescription = null
    ) {
        parent::__construct($id, $titre, $description, $dateDebut, $dateFin, $ville, $dept, $lienInfo, $skillsIds, $projectId, $linkedGroupId, $longDescription);
    }

    public function getType(): string { return 'projet'; }
}

// Compatibilité avec l'ancien identifiant français, si un appelant le référence encore.
if (!class_exists('ProjetPersonnel', false)) {
    class_alias(ProjectPersonnel::class, 'ProjetPersonnel');
}
