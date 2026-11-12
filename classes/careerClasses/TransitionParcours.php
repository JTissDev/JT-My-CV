<?php
declare(strict_types=1);

require_once __DIR__ . '/TimelineItemInterface.php';

/** Intertitre narratif placé avant l'étape cible, selon registre.transitions.<id>.next. */
class TransitionParcours implements TimelineItemInterface
{
    private string $id;
    private string $titre;
    /** @var list<string> */
    private array $description;
    private string $fromId;
    private string $toId;
    private string $dateDebut;
    private string $dateFin;

    /** @param list<string> $description */
    public function __construct(string $id, string $titre, array $description, string $fromId, string $toId, string $dateDebut = '', string $dateFin = '')
    {
        $this->id = trim($id);
        $this->titre = trim($titre);
        $this->description = array_values(array_filter(array_map(
            static fn ($v): string => is_scalar($v) ? trim((string) $v) : '', $description
        ), static fn (string $v): bool => $v !== ''));
        $this->fromId = trim($fromId);
        $this->toId = trim($toId);
        $this->dateDebut = trim($dateDebut);
        $this->dateFin = trim($dateFin);
    }

    public function getId(): string { return $this->id; }
    public function getTitre(): string { return $this->titre; }
    /** @return list<string> */
    public function getDescription(): array { return $this->description; }
    public function getFromId(): string { return $this->fromId; }
    public function getToId(): string { return $this->toId; }
    public function getDateDebut(): string { return $this->dateDebut; }
    public function getDateFin(): string { return $this->dateFin; }
    public function getType(): string { return 'transition'; }
}
