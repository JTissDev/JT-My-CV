<?php
declare(strict_types=1);

require_once __DIR__ . '/TimelineItemInterface.php';

/** Données métier communes à une étape de carrière. Aucune logique de rendu HTML. */
abstract class EvenementParcours implements TimelineItemInterface
{
    protected string $id;
    protected string $titre;
    /** @var list<string> */
    protected array $description;
    /** @var list<string>|null */
    protected ?array $longdesc;
    protected string $dateDebut;
    protected string $dateFin;
    protected string $ville;
    protected string $dept;
    protected bool $lienInfo;
    /** @var list<string> */
    protected array $skillsIds;
    protected ?string $projectId;
    protected ?string $linkedGroupId;

    /**
     * Les paramètres portent les noms des champs métier (appel possible par arguments nommés).
     * longDescription est volontairement à la fin : elle ne peut plus décaler dateDebut/dateFin.
     * @param list<string> $description
     * @param list<string>|null $longDescription
     * @param list<string> $skillsIds
     */
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
        $this->id = trim($id);
        $this->titre = trim($titre);
        $this->description = self::cleanParagraphs($description);
        $this->dateDebut = trim($dateDebut);
        $this->dateFin = trim($dateFin);
        $this->ville = trim($ville);
        $this->dept = trim($dept);
        $this->lienInfo = $lienInfo;
        $this->skillsIds = array_values(array_filter($skillsIds, 'is_string'));
        $this->projectId = self::nullableString($projectId);
        $this->linkedGroupId = self::nullableString($linkedGroupId);
        $this->longdesc = $longDescription === null ? null : self::cleanParagraphs($longDescription);
    }

    /** @param array<mixed> $paragraphs @return list<string> */
    private static function cleanParagraphs(array $paragraphs): array
    {
        return array_values(array_filter(array_map(
            static fn ($value): string => is_scalar($value) ? trim((string) $value) : '',
            $paragraphs
        ), static fn (string $value): bool => $value !== ''));
    }

    private static function nullableString(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);
        return $value === '' ? null : $value;
    }

    public function getId(): string { return $this->id; }
    public function getTitre(): string { return $this->titre; }
    /** @return list<string> */
    public function getDescription(): array { return $this->description; }
    /** @return list<string>|null */
    public function getLongDescription(): ?array { return $this->longdesc; }
    public function hasLongDescription(): bool { return $this->longdesc !== null && $this->longdesc !== []; }
    public function getDateDebut(): string { return $this->dateDebut; }
    public function getDateFin(): string { return $this->dateFin; }
    public function getVille(): string { return $this->ville; }
    public function getDept(): string { return $this->dept; }
    public function hasLienInfo(): bool { return $this->lienInfo; }
    /** @return list<string> */
    public function getSkillsIds(): array { return $this->skillsIds; }
    public function hasProject(): bool { return $this->projectId !== null; }
    public function getProjectId(): ?string { return $this->projectId; }
    public function getLinkedGroupId(): ?string { return $this->linkedGroupId; }
    public function hasLinkedGroup(): bool { return $this->linkedGroupId !== null; }
    abstract public function getType(): string;
}
