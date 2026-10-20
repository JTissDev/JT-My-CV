<?php

/**
 * Fichier : src/components/Button.php
 * Rôle : Système hiérarchique orienté objet pour les boutons (SOCLE + Filles)
 */

if (!defined('APP_STARTED')) {
    die('Accès direct interdit');
}

/**
 * Classe Abstraite Mère
 */
abstract class Button
{
    protected string $label;
    protected array $classes = ['btn']; // Classe universelle de base

    public function __construct(string $label, string|array $extraClasses = [])
    {
        $this->label = $label;

        // Si les classes contextuelles sont passées sous forme de chaîne, on les transforme en tableau
        if (is_string($extraClasses) && !empty($extraClasses)) {
            $extraClasses = explode(' ', trim($extraClasses));
        }

        // On fusionne la base avec les classes contextuelles de l'emplacement
        $this->classes = array_merge($this->classes, $extraClasses);
    }

    // Méthode pour ajouter dynamiquement une classe
    public function addClass(string $class): self
    {
        $this->classes[] = $class;
        return $this;
    }

    // Méthode utilitaire pour générer l'attribut class proprement (sans doublons)
    protected function renderClasses(): string
    {
        return htmlspecialchars(implode(' ', array_unique($this->classes)));
    }

    // Force chaque classe fille à implémenter son rendu HTML
    abstract public function render(): string;
}


/**
 * Fille 1 : Le bouton de type Lien (<a href="...">)
 */
class LinkButton extends Button 
{
    private string $url;

    public function __construct(string $label, string $url, string|array $extraClasses = []) 
    {
        parent::__construct($label, $extraClasses);
        $this->url = $url; // <--- C'est cette ligne qui manquait !
        $this->classes[] = 'btn-link';
    }

    public function render(): string 
    {
        return sprintf(
            '<a href="%s" class="%s">%s</a>',
            htmlspecialchars($this->url),
            $this->renderClasses(),
            htmlspecialchars($this->label)
        );
    }
}


/**
 * Fille 2 : Le bouton de filtre (pour la page des créations)
 */
class FilterButton extends Button
{
    private string $filterValue;
    private bool $isActive;

    public function __construct(string $label, string $filterValue, bool $isActive = false, string|array $extraClasses = [])
    {
        parent::__construct($label, $extraClasses);
        $this->filterValue = $filterValue;
        $this->isActive = $isActive;
        $this->classes[] = 'btn-filter';
    }

    public function render(): string
    {
        // Ajout d'une classe modificateur si le filtre est actif
        $activeClass = $this->isActive ? ' is-active' : '';

        return sprintf(
            '<button type="button" class="%s%s" data-filter="%s">%s</button>',
            $this->renderClasses(),
            $activeClass,
            htmlspecialchars($this->filterValue),
            htmlspecialchars($this->label)
        );
    }
}


/**
 * Fille 3 : Le bouton d'action standard (<button type="submit|button">)
 */
class ActionButton extends Button
{
    private string $type;
    private ?string $name;
    private ?string $value;

    public function __construct(string $label, string $type = 'button', ?string $name = null, ?string $value = null, string|array $extraClasses = [])
    {
        parent::__construct($label, $extraClasses);
        $this->type = $type;
        $this->name = $name;
        $this->value = $value;
        $this->classes[] = 'btn-action';
    }

    public function render(): string
    {
        $nameAttr = $this->name ? sprintf(' name="%s"', htmlspecialchars($this->name)) : '';
        $valueAttr = $this->value !== null ? sprintf(' value="%s"', htmlspecialchars($this->value)) : '';

        return sprintf(
            '<button type="%s"%s%s class="%s">%s</button>',
            htmlspecialchars($this->type),
            $nameAttr,
            $valueAttr,
            $this->renderClasses(),
            htmlspecialchars($this->label)
        );
    }
}
