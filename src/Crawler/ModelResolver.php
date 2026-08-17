<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Crawler;

use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Classes\Locales;

class ModelResolver
{
    /**
     * URL-pad naar model, per site. Een site die nog niet gewarmd is, komt
     * niet als sleutel voor; een gewarmde site zonder enig gekoppeld model
     * staat er wel in, als lege array. Dat onderscheid voorkomt dat elke
     * aanroep voor zo'n site de warmup opnieuw doet.
     *
     * Levenscyclus-contract: deze cache leeft voor het hele PHP-proces en
     * wordt, eenmaal gewarmd voor een site, nooit vanzelf opnieuw gewarmd
     * voor diezelfde site. Wie een audit start moet daarom bij het begin
     * van elke scan expliciet flush() aanroepen, niet alleen bij het
     * wisselen van site. Zonder die flush levert een tweede scan van
     * dezelfde site verouderde koppelingen op: een pagina die na de eerste
     * scan is aangemaakt resolvet naar null, en een pagina waarvan de slug
     * is gewijzigd houdt zijn oude url vast. Dat is een probleem van foute
     * koppelingen, niet alleen van geheugengebruik.
     *
     * @var array<string, array<string, array{model_type: string, model_id: string}>>
     */
    protected static array $cache = [];

    /**
     * De volledige adressen per site, in dezelfde warmup opgebouwd. Apart van
     * $cache omdat die op pad staat en resolve() precies de koppeling hoort
     * terug te geven en niets meer.
     *
     * @var array<string, array<int, string>>
     */
    protected static array $urls = [];

    public static function resolve(string $url, string $siteId): ?array
    {
        if (! array_key_exists($siteId, self::$cache)) {
            self::warm($siteId);
        }

        $key = self::key($url);

        return self::$cache[$siteId][$key] ?? null;
    }

    /**
     * Elk adres dat het CMS voor deze site kent, uit de gekoppelde modellen.
     * Dit is wat de site volgens de beheeromgeving zou moeten hebben, los van
     * wat de sitemap noemt of waar iets naartoe linkt.
     *
     * @return array<int, string>
     */
    public static function urls(string $siteId): array
    {
        if (! array_key_exists($siteId, self::$cache)) {
            self::warm($siteId);
        }

        return self::$urls[$siteId] ?? [];
    }

    /**
     * Wist de volledige cache, voor alle sites. Nodig tussen twee audits in
     * hetzelfde proces (bijvoorbeeld een queue worker die meerdere sites na
     * elkaar crawlt), anders blijft de cache van eerdere sites in het
     * geheugen staan.
     */
    public static function flush(): void
    {
        self::$cache = [];
        self::$urls = [];
    }

    protected static function warm(string $siteId): void
    {
        self::$cache[$siteId] = [];
        self::$urls[$siteId] = [];

        $site = Sites::get($siteId);
        $localeIds = collect($site['locales'] ?? [])->pluck('id')->all();

        if ($localeIds === []) {
            return;
        }

        $originalLocale = app()->getLocale();

        try {
            foreach (cms()->builder('routeModels') as $routeModel) {
                $class = $routeModel['class'];

                if (! method_exists($class, 'getUrl')) {
                    continue;
                }

                $query = $class::query();
                if (method_exists($class, 'scopeThisSite')) {
                    $query->thisSite($siteId);
                }

                foreach ($query->cursor() as $model) {
                    foreach ($localeIds as $localeId) {
                        Locales::setLocale($localeId);

                        try {
                            $url = $model->getUrl($localeId);
                        } catch (\Throwable) {
                            // Een model dat over zijn eigen url struikelt (bijvoorbeeld
                            // een kapotte parent-keten) mag de rest van de warmup niet
                            // meeslepen; die url blijft dan gewoon onbekend.
                            continue;
                        }

                        if (! is_string($url) || $url === '') {
                            continue;
                        }

                        // Bij een botsing op hetzelfde pad wint de eerst gevonden
                        // koppeling. Zeldzaam (twee modellen met identiek pad), maar
                        // deterministisch: registratievolgorde van routeModels,
                        // daarna oplopende id.
                        if (! isset(self::$cache[$siteId][self::key($url)])) {
                            self::$cache[$siteId][self::key($url)] = [
                                'model_type' => $class,
                                'model_id' => (string) $model->getKey(),
                            ];

                            self::$urls[$siteId][] = $url;
                        }
                    }
                }
            }
        } finally {
            // Ook als de cursor zelf breekt (bijvoorbeeld een querytimeout
            // halverwege) moet de applicatie niet in de verkeerde locale
            // achterblijven voor alles wat na deze warmup draait.
            Locales::setLocale($originalLocale);
        }
    }

    protected static function key(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '/';

        return $path === '/' ? '/' : rtrim($path, '/');
    }
}
