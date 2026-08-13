<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Crawler;

use Dashed\DashedCore\Classes\Locales;
use Dashed\DashedCore\Classes\Sites;

class ModelResolver
{
    /**
     * URL-pad naar model, per site. Een site die nog niet gewarmd is, komt
     * niet als sleutel voor; een gewarmde site zonder enig gekoppeld model
     * staat er wel in, als lege array. Dat onderscheid voorkomt dat elke
     * aanroep voor zo'n site de warmup opnieuw doet.
     *
     * @var array<string, array<string, array{model_type: string, model_id: string}>>
     */
    protected static array $cache = [];

    public static function resolve(string $url, string $siteId): ?array
    {
        if (! array_key_exists($siteId, self::$cache)) {
            self::warm($siteId);
        }

        $key = self::key($url);

        return self::$cache[$siteId][$key] ?? null;
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
    }

    protected static function warm(string $siteId): void
    {
        self::$cache[$siteId] = [];

        $site = Sites::get($siteId);
        $localeIds = collect($site['locales'] ?? [])->pluck('id')->all();

        if ($localeIds === []) {
            return;
        }

        $originalLocale = app()->getLocale();

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
                    self::$cache[$siteId][self::key($url)] ??= [
                        'model_type' => $class,
                        'model_id' => (string) $model->getKey(),
                    ];
                }
            }
        }

        Locales::setLocale($originalLocale);
    }

    protected static function key(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '/';

        return $path === '/' ? '/' : rtrim($path, '/');
    }
}
