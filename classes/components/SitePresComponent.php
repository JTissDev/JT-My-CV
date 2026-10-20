<?php
class SitePresComponent
{
    private stdClass $sitePresRegistre;
    private stdClass $sitePresData;
    private string $lang;

    public function __construct(stdClass $sitePresRegistre, stdClass $sitePresData, string $lang)
    {
        $this->sitePresRegistre = $sitePresRegistre; 
        $this->sitePresData = $sitePresData;
        $this->lang = $lang;
    }

    private function getUrl(string $key): string
    {
        if (isset($this->sitePresRegistre->routes->$key)) {
            $url = getCleanUrl($this->sitePresRegistre->routes->$key, $this->lang);
        } elseif (isset($this->sitePresRegistre->currentpage)) {
            $url = getCleanUrl($this->sitePresRegistre->currentpage, $this->lang);
        } else {
            $url = getCleanUrl('home', $this->lang);
        }
        return $url;
    }

    public function render(): string
    {
        $html = '<section id="site-pres-section" class="presentation">';
        
        if (!empty($this->sitePresData->texts)) {
            $html .= '<div class="site-pres-texts">';
            foreach ($this->sitePresData->texts as $text) {
                $html .= sprintf('<p class="site-pres-texts-paragraphe">%s</p>', htmlspecialchars($text));
            }
            $html .= '</div>';
        }

        if (!empty($this->sitePresData->messages)) {
            $html .= '<div class="site-pres-messages">';
            foreach ($this->sitePresData->messages as $key => $message) {
                $translatedKey = $this->sitePresData->messages_labels->$key ?? ucfirst($key);
                $html .= sprintf(
                    '<p class="message message-%s"><a href="%s" class="message-header">%s : </a>%s</p>',
                    htmlspecialchars($key),
                    htmlspecialchars($this->getUrl($key)),
                    htmlspecialchars($translatedKey),
                    htmlspecialchars($message)
                );
            }
            $html .= '</div>';
        }

        $html .= '</section>';

        return $html;
    }
}