<?php
declare(strict_types=1);

require_once __DIR__ . '/TimelineItemInterface.php';
require_once __DIR__ . '/EvenementParcours.php';
require_once __DIR__ . '/Experience.php';
require_once __DIR__ . '/Etude.php';
require_once __DIR__ . '/ProjectPersonnel.php';
require_once __DIR__ . '/Alternance.php';
require_once __DIR__ . '/TransitionParcours.php';

/** Charge le registre et les textes traduits, puis construit la chronologie métier. */
class CareerManager
{
    private string $registryPath;
    private ?string $languageDirectory;
    private ?stdClass $languageDataOverride;
    private ?stdClass $registryDataOverride;

    /**
     * Par défaut, les données sont cherchées dans <racine>/data, à partir de classes/careerClasses/.
     * $languageDirectory peut être fourni pour un chemin de données personnalisé.
     */
    public function __construct(?string $registryPath = null, ?string $languageDirectory = null, ?stdClass $languageData = null, ?stdClass $registryData = null)
    {
        $projectRoot = dirname(__DIR__, 2);
        $this->registryPath = $registryPath ?? $projectRoot . '/data/registre.json';
        $this->languageDirectory = $languageDirectory;
        $this->languageDataOverride = $languageData;
        $this->registryDataOverride = $registryData;
    }

    /**
     * Liste les événements, événements regroupés en Alternance, et pivots narratifs.
     * Les résultats sont classés par date de début décroissante. Un pivot est associé à
     * l'étape source identifiée par career[].transition et validé par transitions[id].next.
     * @return list<TimelineItemInterface>
     */
    public function getItems(string $langCode = 'fr', ?array $types = null, ?bool $lienInfo = null): array
    {
        if (preg_match('/^[a-z]{2}$/i', $langCode) !== 1) {
            throw new InvalidArgumentException('Code de langue invalide.');
        }
        $langCode = strtolower($langCode);
        $langPath = ($this->languageDirectory ?? dirname($this->registryPath)) . '/cv_' . $langCode . '.json';
        $registry = $this->registryDataOverride ?? self::readJsonObject($this->registryPath);
        $language = $this->languageDataOverride ?? self::readJsonObject($langPath);
        $career = $registry->career ?? null;
        if (!is_array($career)) {
            throw new UnexpectedValueException('Le registre doit contenir un tableau career.');
        }

        $events = [];
        $sourceTransitionIds = [];
        foreach ($career as $raw) {
            if (!$raw instanceof stdClass || !isset($raw->id) || !is_string($raw->id)) {
                continue;
            }
            $id = $raw->id;
            $text = $language->career_texts->$id ?? null;
            $text = $text instanceof stdClass ? $text : new stdClass();
            $title = is_string($text->name ?? null) ? $text->name : $id;
            $description = self::stringList($text->description ?? []);
            $longDescription = self::extractLongDescription($text);
            $common = [
                'id' => $id,
                'titre' => $title,
                'description' => $description,
                'dateDebut' => self::scalarString($raw->dateDebut ?? ''),
                'dateFin' => self::scalarString($raw->dateFin ?? ''),
                'ville' => self::scalarString($raw->ville ?? ''),
                'dept' => self::scalarString($raw->dept ?? ''),
                'lienInfo' => (bool) ($raw->lienInfo ?? false),
                'skillsIds' => self::stringList($raw->skills_ids ?? []),
                'projectId' => self::nullableScalar($raw->projectId ?? null),
                'linkedGroupId' => self::nullableScalar($raw->linkedGroupId ?? null),
                'longDescription' => $longDescription,
            ];
            $category = $raw->category ?? $raw->type ?? '';
            if ($category === 'experience') {
                $event = new Experience(
                    id: $common['id'], titre: $common['titre'], description: $common['description'],
                    dateDebut: $common['dateDebut'], dateFin: $common['dateFin'], ville: $common['ville'],
                    dept: $common['dept'], lienInfo: $common['lienInfo'], skillsIds: $common['skillsIds'],
                    projectId: $common['projectId'], entreprise: self::scalarString($raw->entreprise ?? $raw->etablissement ?? ''),
                    typeContrat: self::scalarString($raw->typeContrat ?? 'Professionnel'),
                    linkedGroupId: $common['linkedGroupId'], longDescription: $common['longDescription']
                );
            } elseif ($category === 'etude') {
                $event = new Etude(
                    id: $common['id'], titre: $common['titre'], description: $common['description'],
                    dateDebut: $common['dateDebut'], dateFin: $common['dateFin'], ville: $common['ville'],
                    dept: $common['dept'], lienInfo: $common['lienInfo'], skillsIds: $common['skillsIds'],
                    projectId: $common['projectId'], etablissement: self::scalarString($raw->etablissement ?? ''),
                    linkedGroupId: $common['linkedGroupId'], longDescription: $common['longDescription']
                );
            } elseif ($category === 'projet') {
                $event = new ProjectPersonnel(
                    id: $common['id'], titre: $common['titre'], description: $common['description'],
                    dateDebut: $common['dateDebut'], dateFin: $common['dateFin'], ville: $common['ville'],
                    dept: $common['dept'], lienInfo: $common['lienInfo'], skillsIds: $common['skillsIds'],
                    projectId: $common['projectId'], linkedGroupId: $common['linkedGroupId'],
                    longDescription: $common['longDescription']
                );
            } else {
                continue;
            }
            $events[] = $event;
            $transitionId = self::nullableScalar($raw->transition ?? null);
            if ($transitionId !== null) {
                $sourceTransitionIds[$id] = $transitionId;
            }
        }

        $timeline = self::mergeAlternances($events);
        $timeline = self::insertTransitions($timeline, $sourceTransitionIds, $registry, $language);
        usort($timeline, [self::class, 'compareTimelineItems']);
        if ($types !== null) {
            $allowedTypes = array_values(array_filter($types, 'is_string'));
            $timeline = array_values(array_filter($timeline, static fn (TimelineItemInterface $item): bool => in_array($item->getType(), $allowedTypes, true)));
        }
        if ($lienInfo !== null) {
            $timeline = array_values(array_filter($timeline, static function (TimelineItemInterface $item) use ($lienInfo): bool {
                if ($item instanceof EvenementParcours) { return $item->hasLienInfo() === $lienInfo; }
                if ($item instanceof Alternance) {
                    foreach ($item->getItems() as $member) { if ($member->hasLienInfo() === $lienInfo) { return true; } }
                    return false;
                }
                return !$item instanceof TransitionParcours;
            }));
        }
        return $timeline;
    }

    /** Compatibilité avec l'ancien appel CareerManager::getcareerItems('fr'). */
    public static function getcareerItems(string $langCode = 'fr', ?string $registryPath = null, ?string $languageDirectory = null): array
    {
        return (new self($registryPath, $languageDirectory))->getItems($langCode);
    }

    /** @return list<TimelineItemInterface> */
    private static function mergeAlternances(array $events): array
    {
        $groups = [];
        foreach ($events as $event) {
            $groupId = $event->getLinkedGroupId();
            if ($groupId !== null) {
                $groups[$groupId][] = $event;
            }
        }
        $replacements = [];
        $consumed = [];
        foreach ($groups as $groupId => $members) {
            if (count($members) < 2) {
                continue;
            }
            $replacements[] = new Alternance($groupId, $members);
            foreach ($members as $member) {
                $consumed[$member->getId()] = true;
            }
        }
        $timeline = [];
        foreach ($events as $event) {
            if (!isset($consumed[$event->getId()])) {
                $timeline[] = $event;
            }
        }
        return array_merge($timeline, $replacements);
    }

    /** @param array<string,string> $sourceTransitionIds @return list<TimelineItemInterface> */
    private static function insertTransitions(array $timeline, array $sourceTransitionIds, stdClass $registry, stdClass $language): array
    {
        $eventsById = [];
        foreach ($timeline as $item) {
            if ($item instanceof EvenementParcours) {
                $eventsById[$item->getId()] = $item;
            } elseif ($item instanceof Alternance) {
                foreach ($item->getItems() as $member) {
                    $eventsById[$member->getId()] = $item;
                }
            }
        }
        $transitionsData = $registry->transitions ?? null;
        $transitionTexts = $language->transitions_texts ?? null;
        if (!$transitionsData instanceof stdClass || !$transitionTexts instanceof stdClass) {
            return $timeline;
        }
        $result = [];
        $added = [];
        foreach ($timeline as $item) {
            $result[] = $item;
            $memberIds = $item instanceof Alternance
                ? array_map(static fn (EvenementParcours $event): string => $event->getId(), $item->getItems())
                : ($item instanceof EvenementParcours ? [$item->getId()] : []);
            foreach ($memberIds as $sourceId) {
                $transitionId = $sourceTransitionIds[$sourceId] ?? null;
                $config = $transitionId !== null ? ($transitionsData->$transitionId ?? null) : null;
                $targetId = $config instanceof stdClass ? self::nullableScalar($config->next ?? null) : null;
                $text = $transitionId !== null ? ($transitionTexts->$transitionId ?? null) : null;
                if ($transitionId === null || $targetId === null || !isset($eventsById[$targetId]) || !$text instanceof stdClass || isset($added[$transitionId])) {
                    continue;
                }
                $target = $eventsById[$targetId];
                $result[] = new TransitionParcours(
                    $transitionId,
                    is_string($text->title ?? null) ? $text->title : $transitionId,
                    self::stringList($text->content ?? []),
                    $sourceId,
                    $targetId,
                    $target instanceof TimelineItemInterface ? $target->getDateDebut() : ''
                );
                $added[$transitionId] = true;
            }
        }
        return $result;
    }

    /** @param TimelineItemInterface $a @param TimelineItemInterface $b */
    public static function compareTimelineItems(TimelineItemInterface $a, TimelineItemInterface $b): int
    {
        $yearA = self::year($a->getDateDebut());
        $yearB = self::year($b->getDateDebut());
        if ($yearA !== $yearB) {
            return $yearB <=> $yearA;
        }
        // La transition est affichée juste avant l'étape cible, y compris si elle est dans une alternance.
        if ($a instanceof TransitionParcours && self::containsTarget($b, $a->getToId())) { return -1; }
        if ($b instanceof TransitionParcours && self::containsTarget($a, $b->getToId())) { return 1; }
        return strcmp($a->getId(), $b->getId());
    }

    private static function containsTarget(TimelineItemInterface $item, string $targetId): bool
    {
        if ($item->getId() === $targetId) { return true; }
        if ($item instanceof Alternance) {
            foreach ($item->getItems() as $member) { if ($member->getId() === $targetId) { return true; } }
        }
        return false;
    }

    private static function year(string $date): int
    {
        return preg_match('/\d{4}/', $date, $match) === 1 ? (int) $match[0] : 0;
    }

    private static function readJsonObject(string $path): stdClass
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('Fichier JSON introuvable ou illisible : ' . $path);
        }
        try {
            $value = json_decode((string) file_get_contents($path), false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new UnexpectedValueException('JSON invalide : ' . $path, 0, $exception);
        }
        if (!$value instanceof stdClass) {
            throw new UnexpectedValueException('La racine JSON doit être un objet : ' . $path);
        }
        return $value;
    }

    /** @return list<string> */
    private static function stringList($value): array
    {
        if (!is_array($value)) {
            return [];
        }
        return array_values(array_filter(array_map(
            static fn ($item): string => is_scalar($item) ? trim((string) $item) : '', $value
        ), static fn (string $item): bool => $item !== ''));
    }

    private static function scalarString($value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private static function nullableScalar($value): ?string
    {
        if (!is_scalar($value)) { return null; }
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private static function extractLongDescription(stdClass $text): ?array
    {
        $long = $text->long_description ?? null;
        if (!$long instanceof stdClass || !is_array($long->content ?? null)) {
            return null;
        }
        return self::stringList($long->content);
    }
}
