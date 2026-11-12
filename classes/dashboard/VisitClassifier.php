<?php
declare(strict_types=1);

/**
 * Fichier : classes/dashboard/VisitClassifier.php
 * Rôle : Décide du profil d'une visite (test, bot, human) et reconnaît l'appareil d'un visiteur humain.
 *
 * Règles de docs/dashboard-vie-privee.md, § 4 (premier critère satisfait) :
 *   1. test  : le User-Agent contient « JTissier-CheckBot » (mes scripts de contrôle) ;
 *   4. bot   : User-Agent absent, robot nommé, outil en ligne de commande ou bibliothèque HTTP, navigateur sans écran,
 *              aperçu de lien, ou annonce d'un robot par une adresse « +http(s)://… » ;
 *   5. human : tout le reste.
 * Les profils guided et developer (critères 2 et 3) dépendent des cookies de G5 et G6 : ils s'ajouteront à ce
 * classement, qui n'en lit aucun.
 *
 * Le classement ne repose que sur le User-Agent, déclaratif : un robot qui se fait passer pour un navigateur sera
 * compté comme humain. C'est une limite connue de toute mesure d'audience sans script ni cookie.
 *
 * Pour un humain, on ne garde que des familles grossières (appareil, navigateur, système), jamais le User-Agent.
 * Compatible PHP 8.0.
 */
require_once __DIR__ . '/VisitText.php';

final class VisitClassifier
{
    public const PROFILE_TEST  = 'test';
    public const PROFILE_BOT   = 'bot';
    public const PROFILE_HUMAN = 'human';

    /** Marque du robot de test : présente dans le User-Agent de tous mes scripts de contrôle. */
    public const TEST_MARKER = 'JTissier-CheckBot';

    /** Taille maximale du User-Agent analysé (même borne que la clé de visite). */
    private const MAX_USER_AGENT = 512;

    /**
     * Robots reconnus : fragment du User-Agent (sans tenir compte de la casse) => nom affiché dans le dashboard.
     * Le premier fragment trouvé l'emporte : les plus précis d'abord.
     */
    private const KNOWN_BOTS = [
        // Moteurs de recherche
        'Googlebot'              => 'Googlebot',
        'AdsBot-Google'          => 'Google Ads',
        'Mediapartners-Google'   => 'Google AdSense',
        'Google-InspectionTool'  => 'Google Inspection',
        'Google-Read-Aloud'      => 'Google Read Aloud',
        'Google Favicon'         => 'Google Favicon',
        'GoogleOther'            => 'GoogleOther',
        'Storebot-Google'        => 'Google Storebot',
        'FeedFetcher-Google'     => 'Google Feed Fetcher',
        'bingbot'                => 'Bingbot',
        'BingPreview'            => 'Bing Preview',
        'msnbot'                 => 'MSNBot',
        'Slurp'                  => 'Yahoo Slurp',
        'DuckDuckBot'            => 'DuckDuckBot',
        'DuckDuckGo-Favicons'    => 'DuckDuckGo Favicons',
        'Baiduspider'            => 'Baiduspider',
        'YandexBot'              => 'YandexBot',
        'Yandex'                 => 'Yandex',
        'Sogou web spider'       => 'Sogou',
        'Exabot'                 => 'Exabot',
        'Applebot'               => 'Applebot',
        'Qwantify'               => 'Qwant',
        'SeznamBot'              => 'SeznamBot',
        // Intelligences artificielles
        'GPTBot'                 => 'GPTBot',
        'ChatGPT-User'           => 'ChatGPT',
        'OAI-SearchBot'          => 'OpenAI Search',
        'ClaudeBot'              => 'ClaudeBot',
        'Claude-Web'             => 'Claude',
        'anthropic-ai'           => 'Anthropic',
        'PerplexityBot'          => 'PerplexityBot',
        'Perplexity-User'        => 'Perplexity',
        'CCBot'                  => 'CCBot',
        'Bytespider'             => 'Bytespider',
        'Amazonbot'              => 'Amazonbot',
        'cohere-ai'              => 'Cohere',
        'Meta-ExternalAgent'     => 'Meta External Agent',
        // Aperçus de liens et réseaux sociaux
        'facebookexternalhit'    => 'Facebook (aperçu)',
        'Facebot'                => 'Facebook (aperçu)',
        'TelegramBot'            => 'TelegramBot',   // avant Twitterbot : son User-Agent contient « like TwitterBot »
        'Twitterbot'             => 'Twitterbot',
        'LinkedInBot'            => 'LinkedInBot',
        'WhatsApp'               => 'WhatsApp (aperçu)',
        'Slackbot'               => 'Slackbot',
        'Slack-ImgProxy'         => 'Slack (image)',
        'Discordbot'             => 'Discordbot',
        'SkypeUriPreview'        => 'Skype (aperçu)',
        'Pinterestbot'           => 'Pinterest',
        'redditbot'              => 'Reddit',
        'Mastodon'               => 'Mastodon',
        'Embedly'                => 'Embedly',
        'Iframely'               => 'Iframely',
        // Référencement et analyse
        'AhrefsBot'              => 'AhrefsBot',
        'SemrushBot'             => 'SemrushBot',
        'MJ12bot'                => 'Majestic',
        'DotBot'                 => 'DotBot',
        'PetalBot'               => 'PetalBot',
        'BLEXBot'                => 'BLEXBot',
        'DataForSeoBot'          => 'DataForSEO',
        'ia_archiver'            => 'Alexa Archive',
        'archive.org_bot'        => 'Internet Archive',
        'Screaming Frog'         => 'Screaming Frog',
        // Mesure de performance et surveillance
        'Chrome-Lighthouse'      => 'Lighthouse',
        'Lighthouse'             => 'Lighthouse',
        'PTST'                   => 'WebPageTest',
        'GTmetrix'               => 'GTmetrix',
        'UptimeRobot'            => 'UptimeRobot',
        'Pingdom'                => 'Pingdom',
        'StatusCake'             => 'StatusCake',
        'Site24x7'               => 'Site24x7',
        'Uptime-Kuma'            => 'Uptime Kuma',
        'Better Uptime'          => 'Better Uptime',
        'NewRelicPinger'         => 'New Relic',
        // Navigateurs sans écran et automatisation
        'HeadlessChrome'         => 'Chrome sans écran',
        'PhantomJS'              => 'PhantomJS',
        'Puppeteer'              => 'Puppeteer',
        'Playwright'             => 'Playwright',
        'Selenium'               => 'Selenium',
        // Outils et bibliothèques HTTP
        'curl'                   => 'curl',
        'Wget'                   => 'Wget',
        'python-requests'        => 'python-requests',
        'python-urllib'          => 'python-urllib',
        'aiohttp'                => 'aiohttp',
        'httpx'                  => 'httpx',
        'Go-http-client'         => 'Go http',
        'okhttp'                 => 'OkHttp',
        'libwww-perl'            => 'libwww-perl',
        'Scrapy'                 => 'Scrapy',
        'node-fetch'             => 'node-fetch',
        'axios'                  => 'axios',
        'PostmanRuntime'         => 'Postman',
        'insomnia'               => 'Insomnia',
        'Java/'                  => 'Java',
        'Apache-HttpClient'      => 'Apache HttpClient',
        'GuzzleHttp'             => 'Guzzle',
        'Dalvik'                 => 'Android (application)',
    ];

    /**
     * Marques génériques de robot, pour ceux qui ne figurent pas dans la liste : mot « bot », « crawler », « spider »…
     * Le « bot » du téléphone Cubot n'est pas un robot. Aucun navigateur grand public ne contient ces mots.
     */
    private const GENERIC_BOT_PATTERN = '~(?<!cu)bot|crawl|spider|slurp|scrap|fetch|probe|monitor|scan|check|archiv|headless|http-?client|\+https?://~i';

    private function __construct()
    {
    }

    /**
     * Profil, robot reconnu et appareil d'après le User-Agent.
     *
     * @return array{profile:string, bot_agent:?string, device_type:string, browser:?string, os:?string}
     *         bot_agent : nom du robot reconnu, sinon User-Agent nettoyé et borné à 255 caractères ; null pour un humain
     *         device_type : desktop, mobile, tablet ou unknown (toujours unknown pour un robot ou un test)
     */
    public static function classify(string $userAgent): array
    {
        $ua = substr($userAgent, 0, self::MAX_USER_AGENT);

        if (strpos($ua, self::TEST_MARKER) !== false) {
            return self::nonHuman(self::PROFILE_TEST, VisitText::userAgent($ua));
        }

        $name = self::botName($ua);
        if ($name !== null) {
            return self::nonHuman(self::PROFILE_BOT, $name);
        }

        return [
            'profile'     => self::PROFILE_HUMAN,
            'bot_agent'   => null,
            'device_type' => self::deviceType($ua),
            'browser'     => self::browser($ua),
            'os'          => self::operatingSystem($ua),
        ];
    }

    /**
     * Nom du robot, ou null si le User-Agent est celui d'un navigateur.
     * Un User-Agent absent ou vide est un robot (« Robot sans User-Agent »). Un User-Agent qui ne commence ni par
     * « Mozilla/ » ni par « Opera/ » est celui d'une bibliothèque ou d'un outil, pas d'un navigateur.
     */
    public static function botName(string $userAgent): ?string
    {
        $ua = trim(substr($userAgent, 0, self::MAX_USER_AGENT));

        if ($ua === '' || $ua === '-') {
            return 'Robot sans User-Agent';
        }

        foreach (self::KNOWN_BOTS as $fragment => $name) {
            if (stripos($ua, $fragment) !== false) {
                return $name;
            }
        }

        if (preg_match(self::GENERIC_BOT_PATTERN, $ua) === 1 || (strpos($ua, 'Mozilla/') !== 0 && strpos($ua, 'Opera/') !== 0)) {
            return VisitText::userAgent($ua);
        }

        return null;
    }

    /**
     * Vrai si le visiteur demande à ne pas être suivi : en-tête DNT à 1 ou Sec-GPC à 1 (décision D1).
     * Aucune clé de visite ni aucun parcours pour lui, seulement le compteur quotidien anonyme.
     *
     * @param array<string, mixed> $server typiquement $_SERVER
     */
    public static function optedOut(array $server): bool
    {
        foreach (['HTTP_DNT', 'HTTP_SEC_GPC'] as $header) {
            $value = $server[$header] ?? null;
            if (is_string($value) && trim($value) === '1') {
                return true;
            }
        }

        return false;
    }

    /**
     * Type d'appareil : tablet, mobile ou desktop.
     * Un Android sans le mot « Mobile » est une tablette. Un iPad récent se présente comme un Mac : il sera compté
     * comme ordinateur (limite connue de tout classement par User-Agent).
     */
    public static function deviceType(string $userAgent): string
    {
        $ua = $userAgent;

        if (preg_match('/iPad|Tablet|PlayBook|Kindle|Silk\//i', $ua) === 1) {
            return 'tablet';
        }

        if (preg_match('/iPhone|iPod|Windows Phone|IEMobile|Opera Mini|Opera Mobi|BlackBerry|Mobile/i', $ua) === 1) {
            return 'mobile';
        }

        return stripos($ua, 'Android') !== false ? 'tablet' : 'desktop';
    }

    /** Famille du navigateur (la plus précise d'abord : Edge, Opera et Samsung contiennent aussi « Chrome »). */
    public static function browser(string $userAgent): string
    {
        $ua = $userAgent;

        if (preg_match('~Edg/|EdgA/|EdgiOS/|Edge/~', $ua) === 1) {
            return 'Edge';
        }
        if (preg_match('~OPR/|Opera|OPiOS/~', $ua) === 1) {
            return 'Opera';
        }
        if (strpos($ua, 'SamsungBrowser/') !== false) {
            return 'Samsung Internet';
        }
        if (preg_match('~Firefox/|FxiOS/~', $ua) === 1) {
            return 'Firefox';
        }
        // Navigateur intégré à une application (LinkedIn, Instagram, Facebook…) : « wv » sur Android,
        // aucune mention de Safari sur iOS. Ce n'est pas le navigateur du visiteur, mais c'est bien un humain.
        if (strpos($ua, '; wv)') !== false
            || (strpos($ua, 'AppleWebKit') !== false && strpos($ua, 'Safari/') === false && preg_match('/iPhone|iPad|iPod/', $ua) === 1)) {
            return 'App WebView';
        }
        if (preg_match('~Chrome/|CriOS/|Chromium/~', $ua) === 1) {
            return 'Chrome';
        }
        if (strpos($ua, 'Safari/') !== false && strpos($ua, 'Version/') !== false) {
            return 'Safari';
        }
        if (preg_match('~MSIE |Trident/~', $ua) === 1) {
            return 'Internet Explorer';
        }

        return 'Other';
    }

    /** Famille du système (Android avant Linux, iOS avant macOS : leurs User-Agent contiennent aussi « Linux » et « Mac OS X »). */
    public static function operatingSystem(string $userAgent): string
    {
        $ua = $userAgent;

        if (preg_match('/Windows Phone/i', $ua) === 1) {
            return 'Windows Phone';
        }
        if (strpos($ua, 'Windows') !== false) {
            return 'Windows';
        }
        if (preg_match('/iPhone|iPad|iPod/', $ua) === 1) {
            return 'iOS';
        }
        if (stripos($ua, 'Android') !== false) {
            return 'Android';
        }
        if (strpos($ua, 'CrOS') !== false) {
            return 'ChromeOS';
        }
        if (preg_match('/Macintosh|Mac OS X/', $ua) === 1) {
            return 'macOS';
        }
        if (preg_match('/Linux|X11/', $ua) === 1) {
            return 'Linux';
        }

        return 'Other';
    }

    /**
     * @return array{profile:string, bot_agent:?string, device_type:string, browser:?string, os:?string}
     */
    private static function nonHuman(string $profile, string $agent): array
    {
        return [
            'profile'     => $profile,
            'bot_agent'   => $agent,
            'device_type' => 'unknown',
            'browser'     => null,
            'os'          => null,
        ];
    }
}
