<?php
class HeroPresComponent
{
    private stdClass $heroPresRegistre;
    private stdClass $heroPresData;
    private string $lang;
    private string $headingTag;

    public function __construct(stdClass $heroPresRegistre, stdClass $heroPresData, string $lang, string $headingTag = 'h3')
    {
        $this->heroPresRegistre = $heroPresRegistre;
        $this->heroPresData = $heroPresData;
        $this->lang = $lang;
        $this->headingTag = in_array(strtolower($headingTag), ['h2', 'h3', 'h4', 'h5']) ? $headingTag : 'h3';
    }

    public function render(): string
    {
        $html = '<section id="hero-pres-section" class="presentation">';
        $html .= sprintf('<%s class="presentation-title">%s</%s>', $this->headingTag, $this->heroPresData->title, $this->headingTag);
        if (!empty($this->heroPresData->intro)) {
            $html .= '<div class="hero-pres-div">';
            foreach ($this->heroPresData->intro as $intro) {
                $html .= sprintf('<p class="hero-pres-intro">%s</p>', htmlspecialchars($intro));
            }
            $html .= '</div>';
        }

        if (!empty($this->heroPresData->languages)) {
            $html .= '<ul class="hero-devLanguage-list">';
            foreach ($this->heroPresData->languages as $langItem) {
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
