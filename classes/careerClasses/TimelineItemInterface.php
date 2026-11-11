<?php
declare(strict_types=1);

/** Contrat commun des éléments affichables dans la frise. */
interface TimelineItemInterface
{
    public function getId(): string;
    public function getTitre(): string;
    public function getDateDebut(): string;
    public function getDateFin(): string;
    public function getType(): string;
}
