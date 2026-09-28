<?php
/**
 * Classe abstraite représentant le socle commun de tout événement du parcours.
 */
abstract class EvenementParcours {
    protected string $id;
    protected string $titre;         // Vient du fichier de langue (traduisible)
    protected array $description;    // Vient du fichier de langue (paragraphes)
    protected string $dateDebut;
    protected string $dateFin;
    protected string $ville;
    protected string $dept;
    protected bool $lienInfo;
    protected array $skillsIds;
    protected ?string $projectId;

    public function __construct(
        string $id, 
        string $titre,
        array $description,
        string $dateDebut, 
        string $dateFin, 
        string $ville, 
        string $dept, 
        bool $lienInfo, 
        array $skillsIds, 
        ?string $projectId = null
    ) {
        $this->id = $id;
        $this->titre = $titre;
        $this->description = $description;
        $this->dateDebut = $dateDebut;
        $this->dateFin = $dateFin;
        $this->ville = $ville;
        $this->dept = $dept;
        $this->lienInfo = $lienInfo;
        $this->skillsIds = $skillsIds;
        $this->projectId = $projectId;
    }

    // --- GETTERS (Accesseurs) ---

    public function getId(): string {
        return $this->id;
    }

    public function getTitre(): string {
        return $this->titre;
    }

    public function getDescription(): array {
        return $this->description;
    }

    public function getDateDebut(): string {
        return $this->dateDebut;
    }

    public function getDateFin(): string {
        return $this->dateFin;
    }

    public function getVille(): string {
        return $this->ville;
    }

    public function getDept(): string {
        return $this->dept;
    }

    public function hasLienInfo(): bool {
        return $this->lienInfo;
    }

    public function getSkillsIds(): array {
        return $this->skillsIds;
    }

    public function hasProject(): bool {
        return !empty($this->projectId);
    }

    public function getProjectId(): ?string {
        return $this->projectId;
    }
}