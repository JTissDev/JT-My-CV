<?php
declare(strict_types=1);

/**
 * Test de classes/dashboard/VisitClassifier.php : profil (test, bot, human) et appareil d'après le User-Agent.
 * Exécution : php tests/dashboard/test_visit_classifier.php   (ligne de commande uniquement ; aucune base requise)
 *
 * Deux garanties : (1) des User-Agent réels de navigateurs ne sont jamais pris pour des robots, y compris les
 * navigateurs intégrés aux applications (LinkedIn, Instagram) et un téléphone dont le nom finit par « bot » ;
 * (2) les robots courants, les bibliothèques HTTP et les aperçus de liens sont reconnus.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../../classes/dashboard/VisitClassifier.php';

$ok = 0;
$ko = 0;
function check(string $label, bool $cond): void
{
    global $ok, $ko;
    if ($cond) {
        $ok++;
    } else {
        $ko++;
        echo "ÉCHEC : {$label}\n";
    }
}

// --- Humains : [User-Agent, appareil, navigateur, système]
$humans = [
    'Chrome Windows'      => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows'],
    'Chrome macOS'        => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'macOS'],
    'Chrome Linux'        => ['Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'Linux'],
    'Chrome ChromeOS'     => ['Mozilla/5.0 (X11; CrOS x86_64 14541.0.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36', 'desktop', 'Chrome', 'ChromeOS'],
    'Firefox Windows'     => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:127.0) Gecko/20100101 Firefox/127.0', 'desktop', 'Firefox', 'Windows'],
    'Firefox Linux'       => ['Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:127.0) Gecko/20100101 Firefox/127.0', 'desktop', 'Firefox', 'Linux'],
    'Firefox Android'     => ['Mozilla/5.0 (Android 14; Mobile; rv:127.0) Gecko/127.0 Firefox/127.0', 'mobile', 'Firefox', 'Android'],
    'Firefox iOS'         => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) FxiOS/127.0 Mobile/15E148 Safari/605.1.15', 'mobile', 'Firefox', 'iOS'],
    'Safari macOS'        => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15', 'desktop', 'Safari', 'macOS'],
    'Safari iPhone'       => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1', 'mobile', 'Safari', 'iOS'],
    'Safari iPad'         => ['Mozilla/5.0 (iPad; CPU OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1', 'tablet', 'Safari', 'iOS'],
    'Chrome iOS'          => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/126.0.6478.153 Mobile/15E148 Safari/604.1', 'mobile', 'Chrome', 'iOS'],
    'Edge Windows'        => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 Edg/126.0.0.0', 'desktop', 'Edge', 'Windows'],
    'Edge Android'        => ['Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36 EdgA/126.0.0.0', 'mobile', 'Edge', 'Android'],
    'Chrome Android'      => ['Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36', 'mobile', 'Chrome', 'Android'],
    'Chrome tablette'     => ['Mozilla/5.0 (Linux; Android 13; SM-X700) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36', 'tablet', 'Chrome', 'Android'],
    'Samsung Internet'    => ['Mozilla/5.0 (Linux; Android 14; SAMSUNG SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/25.0 Chrome/121.0.0.0 Mobile Safari/537.36', 'mobile', 'Samsung Internet', 'Android'],
    'Opera Windows'       => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 OPR/111.0.0.0', 'desktop', 'Opera', 'Windows'],
    'Opera Mini'          => ['Opera/9.80 (Android; Opera Mini/36.2.2254/119.132; U; id) Presto/2.12.423 Version/12.16', 'mobile', 'Opera', 'Android'],
    'Internet Explorer'   => ['Mozilla/5.0 (Windows NT 10.0; WOW64; Trident/7.0; rv:11.0) like Gecko', 'desktop', 'Internet Explorer', 'Windows'],
    'DuckDuckGo Android'  => ['Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/126.0.0.0 Mobile Safari/537.36 DuckDuckGo/5', 'mobile', 'Chrome', 'Android'],
    'Yandex Browser'      => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 YaBrowser/24.6.0.0 Safari/537.36', 'desktop', 'Chrome', 'Windows'],
    'Téléphone Cubot'     => ['Mozilla/5.0 (Linux; Android 12; CUBOT_X30) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36', 'mobile', 'Chrome', 'Android'],
    'LinkedIn iOS'        => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 [LinkedInApp]/9.30.1234', 'mobile', 'App WebView', 'iOS'],
    'Instagram Android'   => ['Mozilla/5.0 (Linux; Android 13; Pixel 7 Build/TQ3A.230901.001; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/126.0.6478.122 Mobile Safari/537.36 Instagram 330.0.0.0.0 Android', 'mobile', 'App WebView', 'Android'],
    'Facebook iOS'        => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 [FBAN/FBIOS;FBAV/460.0.0.0.0;FBBV/0;FBDV/iPhone14,2]', 'mobile', 'App WebView', 'iOS'],
    'Windows Phone'       => ['Mozilla/5.0 (Windows Phone 10.0; Android 6.0.1; Microsoft; Lumia 950) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/52.0.2743.116 Mobile Safari/537.36 Edge/15.15063', 'mobile', 'Edge', 'Windows Phone'],
];

foreach ($humans as $label => [$ua, $device, $browser, $os]) {
    $r = VisitClassifier::classify($ua);
    check("{$label} : profil human",          $r['profile'] === 'human');
    check("{$label} : aucun User-Agent gardé", $r['bot_agent'] === null);
    check("{$label} : appareil {$device}",     $r['device_type'] === $device);
    check("{$label} : navigateur {$browser}",  $r['browser'] === $browser);
    check("{$label} : système {$os}",          $r['os'] === $os);
}

$tv = VisitClassifier::classify('Mozilla/5.0 (SMART-TV; Linux; Tizen 6.0) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/4.0 Chrome/76.0.3809.146 TV Safari/537.36');
check('télévision connectée : humaine', $tv['profile'] === 'human');
$console = VisitClassifier::classify('Mozilla/5.0 (Nintendo Switch; WifiWebAuthApplet) AppleWebKit/601.6 (KHTML, like Gecko) NF/4.0.0.5.10 NintendoBrowser/5.1.0.13343');
check('console de jeu : humaine', $console['profile'] === 'human');
$unknownBrowser = VisitClassifier::classify('Mozilla/5.0 (Unknown; Foo) Gecko/1.0 Bar/2.0');
check('navigateur inconnu qui commence par Mozilla : humain, famille Other', $unknownBrowser['profile'] === 'human' && $unknownBrowser['browser'] === 'Other' && $unknownBrowser['os'] === 'Other');

// --- Robots : [User-Agent, nom attendu]
$bots = [
    'Googlebot'            => ['Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', 'Googlebot'],
    'Googlebot mobile'     => ['Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', 'Googlebot'],
    'Google Ads'           => ['AdsBot-Google (+http://www.google.com/adsbot.html)', 'Google Ads'],
    'Google Favicon'       => ['Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 Google Favicon', 'Google Favicon'],
    'Bingbot'              => ['Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)', 'Bingbot'],
    'Yahoo Slurp'          => ['Mozilla/5.0 (compatible; Yahoo! Slurp; http://help.yahoo.com/help/us/ysearch/slurp)', 'Yahoo Slurp'],
    'DuckDuckBot'          => ['DuckDuckBot/1.1; (+http://duckduckgo.com/duckduckbot.html)', 'DuckDuckBot'],
    'Baiduspider'          => ['Mozilla/5.0 (compatible; Baiduspider/2.0; +http://www.baidu.com/search/spider.html)', 'Baiduspider'],
    'YandexBot'            => ['Mozilla/5.0 (compatible; YandexBot/3.0; +http://yandex.com/bots)', 'YandexBot'],
    'Applebot'             => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_5) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.1.1 Safari/605.1.15 (Applebot/0.1; +http://www.apple.com/go/applebot)', 'Applebot'],
    'Facebook'             => ['facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)', 'Facebook (aperçu)'],
    'Twitterbot'           => ['Twitterbot/1.0', 'Twitterbot'],
    'LinkedInBot'          => ['LinkedInBot/1.0 (compatible; Mozilla/5.0; Apache-HttpClient +http://www.linkedin.com)', 'LinkedInBot'],
    'WhatsApp'             => ['WhatsApp/2.23.20.0 A', 'WhatsApp (aperçu)'],
    'Slackbot'             => ['Slackbot-LinkExpanding 1.0 (+https://api.slack.com/robots)', 'Slackbot'],
    'Telegram'             => ['TelegramBot (like TwitterBot)', 'TelegramBot'],
    'Discordbot'           => ['Mozilla/5.0 (compatible; Discordbot/2.0; +https://discordapp.com)', 'Discordbot'],
    'Skype'                => ['Mozilla/5.0 (Windows NT 6.1; WOW64) SkypeUriPreview Preview/0.5', 'Skype (aperçu)'],
    'curl'                 => ['curl/8.5.0', 'curl'],
    'Wget'                 => ['Wget/1.21.4', 'Wget'],
    'python-requests'      => ['python-requests/2.31.0', 'python-requests'],
    'Go'                   => ['Go-http-client/2.0', 'Go http'],
    'Java'                 => ['Java/17.0.2', 'Java'],
    'OkHttp'               => ['okhttp/4.12.0', 'OkHttp'],
    'axios'                => ['axios/1.6.8', 'axios'],
    'node-fetch'           => ['node-fetch/1.0 (+https://github.com/bitinn/node-fetch)', 'node-fetch'],
    'Postman'              => ['PostmanRuntime/7.37.3', 'Postman'],
    'Chrome sans écran'    => ['Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/126.0.0.0 Safari/537.36', 'Chrome sans écran'],
    'Lighthouse'           => ['Mozilla/5.0 (Linux; Android 11; moto g power (2022)) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/109.0.0.0 Mobile Safari/537.36 Chrome-Lighthouse', 'Lighthouse'],
    'UptimeRobot'          => ['Mozilla/5.0 (compatible; UptimeRobot/2.0; http://www.uptimerobot.com/)', 'UptimeRobot'],
    'GPTBot'               => ['Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; GPTBot/1.1; +https://openai.com/gptbot', 'GPTBot'],
    'ClaudeBot'            => ['Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', 'ClaudeBot'],
    'CCBot'                => ['CCBot/2.0 (https://commoncrawl.org/faq/)', 'CCBot'],
    'AhrefsBot'            => ['Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 'AhrefsBot'],
    'SemrushBot'           => ['Mozilla/5.0 (compatible; SemrushBot/7~bl; +http://www.semrush.com/bot.html)', 'SemrushBot'],
    'Majestic'             => ['Mozilla/5.0 (compatible; MJ12bot/v1.4.8; http://mj12bot.com/)', 'Majestic'],
    'Bytespider'           => ['Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 'Bytespider'],
    'PetalBot'             => ['Mozilla/5.0 (Linux; Android 7.0;) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; PetalBot;+https://webmaster.petalsearch.com/site/petalbot)', 'PetalBot'],
    'Amazonbot'            => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_5) AppleWebKit/600.2.5 (KHTML, like Gecko) Version/8.0.2 Safari/600.2.5 (Amazonbot/0.1; +https://developer.amazon.com/support/amazonbot)', 'Amazonbot'],
    'ia_archiver'          => ['ia_archiver (+http://www.alexa.com/site/help/webmasters; crawler@alexa.com)', 'Alexa Archive'],
    'Sans User-Agent'      => ['', 'Robot sans User-Agent'],
    'Tiret seul'           => ['-', 'Robot sans User-Agent'],
    'Espaces seuls'        => ['   ', 'Robot sans User-Agent'],
    'casse ignorée'        => ['GOOGLEBOT/2.1', 'Googlebot'],
];

foreach ($bots as $label => [$ua, $name]) {
    $r = VisitClassifier::classify($ua);
    check("{$label} : profil bot",      $r['profile'] === 'bot');
    check("{$label} : nom « {$name} »", $r['bot_agent'] === $name);
    check("{$label} : appareil inconnu, aucun navigateur ni système", $r['device_type'] === 'unknown' && $r['browser'] === null && $r['os'] === null);
}

// --- Robots inconnus : détectés, et le User-Agent nettoyé est conservé à la place du nom
$unknownBots = [
    'mot « bot » dans un nom inconnu' => 'Mozilla/5.0 (compatible; SomethingBot/1.0; +https://example.org/bot)',
    'crawler'                         => 'MyCrawler/2.1',
    'bibliothèque inconnue'           => 'Foo/1.0',
    'adresse « +https » seule'        => 'Mozilla/5.0 (compatible; Zorg; +https://example.org/info)',
    'spider'                          => 'Mozilla/5.0 (compatible; MegaSpider 3.0)',
    'scraper'                         => 'Mozilla/5.0 (compatible; MyScraper)',
    'vérificateur de liens'           => 'Mozilla/5.0 (compatible; LinkChecker/10.0)',
    'moniteur'                        => 'Mozilla/5.0 (compatible; SiteMonitor/2)',
    'ne commence pas par Mozilla'     => 'NotABrowser 1.2',
];
foreach ($unknownBots as $label => $ua) {
    $r = VisitClassifier::classify($ua);
    check("{$label} : profil bot",                    $r['profile'] === 'bot');
    check("{$label} : User-Agent nettoyé conservé",   $r['bot_agent'] === $ua);
}

// --- Test : la marque du robot de test l'emporte sur tout le reste
$t1 = VisitClassifier::classify('JTissier-CheckBot/1.0');
check('test : profil test',                        $t1['profile'] === 'test');
check('test : User-Agent conservé (c\'est le mien)', $t1['bot_agent'] === 'JTissier-CheckBot/1.0');
check('test : appareil inconnu',                   $t1['device_type'] === 'unknown' && $t1['browser'] === null && $t1['os'] === null);
check('test : marque ajoutée à un navigateur',     VisitClassifier::classify('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 JTissier-CheckBot/1.0 (a8)')['profile'] === 'test');
check('test : marque ajoutée à curl',              VisitClassifier::classify('curl/8.5.0 JTissier-CheckBot')['profile'] === 'test');
check('test : marque au début',                    VisitClassifier::classify('JTissier-CheckBot')['profile'] === 'test');
check('test : la casse compte (« jtissier-checkbot » n\'est pas le robot de test)', VisitClassifier::classify('jtissier-checkbot/1.0')['profile'] === 'bot');
check('test : constante', VisitClassifier::TEST_MARKER === 'JTissier-CheckBot');
$long = VisitClassifier::classify(str_repeat('x', 600) . ' JTissier-CheckBot');
check('test : marque au-delà de 512 caractères : non vue (User-Agent borné)', $long['profile'] === 'bot');

// --- Signal « ne pas me suivre » (décision D1)
check('DNT: 1',                          VisitClassifier::optedOut(['HTTP_DNT' => '1']));
check('Sec-GPC: 1',                      VisitClassifier::optedOut(['HTTP_SEC_GPC' => '1']));
check('DNT et GPC',                      VisitClassifier::optedOut(['HTTP_DNT' => '1', 'HTTP_SEC_GPC' => '1']));
check('DNT: 0 : suivi possible',         !VisitClassifier::optedOut(['HTTP_DNT' => '0']));
check('Sec-GPC: 0 : suivi possible',     !VisitClassifier::optedOut(['HTTP_SEC_GPC' => '0']));
check('aucun signal',                    !VisitClassifier::optedOut([]));
check('espaces autour de 1',             VisitClassifier::optedOut(['HTTP_DNT' => ' 1 ']));
check('valeur étrangère ignorée',        !VisitClassifier::optedOut(['HTTP_DNT' => 'yes', 'HTTP_SEC_GPC' => 'true']));
check('valeur non texte ignorée',        !VisitClassifier::optedOut(['HTTP_DNT' => 1, 'HTTP_SEC_GPC' => ['1']]));
check('un seul des deux suffit',         VisitClassifier::optedOut(['HTTP_DNT' => '0', 'HTTP_SEC_GPC' => '1']));

// --- Robustesse : jamais d'exception, toujours une réponse bien formée
mt_srand(20261107);
$malformed = 0;
$humanWithAgent = 0;
for ($i = 0; $i < 3000; $i++) {
    $raw = '';
    for ($j = mt_rand(0, 700); $j > 0; $j--) {
        $raw .= chr(mt_rand(0, 255));
    }
    $r = VisitClassifier::classify($raw);
    $shapeOk = array_keys($r) === ['profile', 'bot_agent', 'device_type', 'browser', 'os']
        && in_array($r['profile'], ['test', 'bot', 'human'], true)
        && in_array($r['device_type'], ['desktop', 'mobile', 'tablet', 'unknown'], true)
        && ($r['bot_agent'] === null || (preg_match('//u', $r['bot_agent']) === 1 && (int) preg_match_all('/./su', $r['bot_agent']) <= 255 && preg_match('/[\p{Cc}]/u', $r['bot_agent']) !== 1));
    if (!$shapeOk) {
        $malformed++;
    }
    if ($r['profile'] === 'human' && $r['bot_agent'] !== null) {
        $humanWithAgent++;
    }
}
check('3000 User-Agent aléatoires : réponse toujours bien formée et stockable', $malformed === 0);
check('un humain ne garde jamais de User-Agent',                                $humanWithAgent === 0);
check('User-Agent de 100 000 caractères : borné et rapide', (static function (): bool {
    $start = microtime(true);
    $r = VisitClassifier::classify(str_repeat('Mozilla/5.0 ', 10000));
    return $r['profile'] === 'human' && (microtime(true) - $start) < 0.5;
})());

$start = microtime(true);
$sample = $humans['Chrome Windows'][0];
for ($i = 0; $i < 5000; $i++) {
    VisitClassifier::classify($sample);
}
check('5000 classements en moins de 3 s (coût par page négligeable)', (microtime(true) - $start) < 3.0);

// --- Le code de production ne lit ni cookie ni session (G5 et G6 les ajouteront à leur livraison)
$source = (string) file_get_contents(__DIR__ . '/../../classes/dashboard/VisitClassifier.php');
check('VisitClassifier : aucune lecture de cookie ni de session', preg_match('/\$_COOKIE|\$_SESSION|setcookie|session_start|set-cookie/i', $source) !== 1);

echo "\nRésultat : {$ok} OK, {$ko} échec(s), 0 section(s) ignorée(s)\n";
exit($ko === 0 ? 0 : 1);
