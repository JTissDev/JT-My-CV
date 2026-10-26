<?php

namespace App\Components;

class Breadcrumb {
    private array $items;

    /**
     * @param array $items Tableau associatif [['name' => 'Accueil', 'url' => '...'], ...]
     */
    public function __construct(array $items) {
        $this->items = $items;
    }

    /**
     * Génère le HTML visuel du fil d'Ariane
     */
    public function render(): string {
        if (empty($this->items)) {
            return '';
        }

        $html = '<nav class="breadcrumb-nav" aria-label="Fil d\'Ariane">';
        $html .= '<div class="breadcrumb-container">';
        $html .= '<ol class="breadcrumb-list">';

        $total = count($this->items);
        foreach ($this->items as $index => $item) {
            $isLast = ($index === $total - 1);
            $name = htmlspecialchars($item['name'] ?? '');

            if ($isLast) {
                // Dernier élément : page active (non cliquable)
                $html .= '<li class="breadcrumb-item is-active" aria-current="page">' . $name . '</li>';
            } else {
                // Éléments précédents (cliquables)
                $url = htmlspecialchars($item['url'] ?? '#');
                $html .= '<li class="breadcrumb-item">';
                $html .= '<a href="' . $url . '">' . $name . '</a>';
                $html .= '<span class="breadcrumb-separator">/</span>';
                $html .= '</li>';
            }
        }

        $html .= '</ol>';
        $html .= '</div>';
        $html .= '</nav>';

        return $html;
    }

    /**
     * Génère le balisage Schema.org en JSON-LD pour le <head>[cite: 1]
     */
    public function renderSchema(): string {
        if (empty($this->items)) {
            return '';
        }

        $schemaItems = [];
        foreach ($this->items as $index => $item) {
            $schemaItems[] = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['name'] ?? '',
                'item' => $item['url'] ?? ''
            ];
        }

        $schemaData = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $schemaItems
        ];

        return '<script type="application/ld+json">' . json_encode($schemaData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
    }
}