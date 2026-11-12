<?php
declare(strict_types=1);

require_once __DIR__ . '/TimelineItemInterface.php';
require_once __DIR__ . '/EvenementParcours.php';

/** Regroupement métier d'étapes partageant un linkedGroupId (formation + expérience). */
class Alternance implements TimelineItemInterface
{
    private string $id;
    private string $linkedGroupId;
    /** @var list<EvenementParcours> */
    private array $items;
    private string $dateDebut;
    private string $dateFin;

    /** @param list<EvenementParcours> $items */
    public function __construct(string $linkedGroupId, array $items)
    {
        $items = array_values(array_filter($items, static fn ($item): bool => $item instanceof EvenementParcours));
        if ($linkedGroupId === '' || count($items) < 2) {
            throw new InvalidArgumentException('Une alternance exige un identifiant de groupe et au moins deux événements.');
        }
        $this->linkedGroupId = $linkedGroupId;
        $this->id = 'alternance:' . $linkedGroupId;
        $this->items = $items;
        $starts = array_map(static fn (EvenementParcours $item): string => $item->getDateDebut(), $items);
        $ends = array_map(static fn (EvenementParcours $item): string => $item->getDateFin(), $items);
        $this->dateDebut = self::boundary($starts, false);
        $this->dateFin = self::boundary($ends, true);
    }

    /** @param list<string> $dates */
    private static function boundary(array $dates, bool $latest): string
    {
        usort($dates, static fn (string $a, string $b): int => self::year($a) <=> self::year($b));
        return $dates === [] ? '' : $dates[$latest ? count($dates) - 1 : 0];
    }
    private static function year(string $date): int { return preg_match('/\d{4}/', $date, $m) ? (int) $m[0] : 0; }

    public function getId(): string { return $this->id; }
    public function getLinkedGroupId(): string { return $this->linkedGroupId; }
    public function getTitre(): string { return 'Alternance'; }
    public function getDateDebut(): string { return $this->dateDebut; }
    public function getDateFin(): string { return $this->dateFin; }
    public function getType(): string { return 'alternance'; }
    /** @return list<EvenementParcours> */
    public function getItems(): array { return $this->items; }
}
