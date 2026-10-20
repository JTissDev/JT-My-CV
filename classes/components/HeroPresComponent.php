<?php
class HeroPresComponent
{
    private stdClass $heroPresRegistre;
    private stdClass $heroPresData;
    private string $lang;

    public function __construct(stdClass $heroPresRegistre, stdClass $heroPresData, string $lang)
    {
        $this->heroPresRegistre = $heroPresRegistre; 
        $this->heroPresData = $heroPresData;
        $this->lang = $lang;
    }

    public function render(): string 
    {
        $html = '<section id="hero-pres-section" class="presentation">';

        if (!empty($this->heroPresData->Intros)) {
            $html .= '<div class="hero-pres-div">';
            foreach ($this->heroPresData->Intros as $intro) {
                $html .= sprintf('<p class="hero-pres-intro">%s</p>', htmlspecialchars($intro));
            }
            $html .= '</div>';
        }

        if (!empty($this->heroPresData->devLanguages)){
            $html .= '<ul class="hero-devLanguage-list">';
            foreach ($this->heroPresData->devLanguages as $langItem) {
                $html .= '<li>';
                $html .= sprintf('<strong>%s :</strong> ', htmlspecialchars($langItem->name));
                $html .= htmlspecialchars($langItem->detail);
                $html .= '</li>';
            }
            $html .= '</ul>';
        }

        if (!empty($this->heroPresData->outro)) {
            $html .= '<div class="hero-pres-div">';
            foreach ($this->heroPresData->outro as $outro) {
                $html .= sprintf('<p class="hero-pres-outro">%s</p>', htmlspecialchars($outro));
            }
            $html .= '</div>';
        }

        $html .= '</section>';
        return $html;
    }
}