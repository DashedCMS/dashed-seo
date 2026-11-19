<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\PageSpeed;

use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedSeo\Settings\PageSpeedSettings;

/**
 * Welke URL's er per site gemeten worden: de homepage, één product en één
 * categorie (als de webshop er is en de beheerder dat niet uitzette), plus
 * wat er bij de instellingen is ingevuld. Hooguit PageSpeedSettings::MAX_URLS.
 */
class PageSpeedUrls
{
    /** @return array<int, string> */
    public static function forSite(string $siteId): array
    {
        $urls = [];

        if (PageSpeedSettings::autoUrls($siteId)) {
            $urls[] = Sites::url('/', $siteId);

            foreach (['Dashed\\DashedEcommerceCore\\Models\\Product', 'Dashed\\DashedEcommerceCore\\Models\\ProductCategory'] as $class) {
                if ($url = self::firstUrl($class, $siteId)) {
                    $urls[] = $url;
                }
            }
        }

        $urls = array_merge($urls, PageSpeedSettings::extraUrls($siteId));

        return array_slice(array_values(array_unique($urls)), 0, PageSpeedSettings::MAX_URLS);
    }

    protected static function firstUrl(string $class, string $siteId): ?string
    {
        if (! class_exists($class)) {
            return null;
        }

        return rescue(function () use ($class, $siteId): ?string {
            $model = $class::query()->publicShowable()->orderBy('id')->first();
            $url = $model?->getUrl();

            if (! is_string($url) || $url === '') {
                return null;
            }

            $url = Sites::url($url, $siteId);

            // Zonder overzichtspagina geeft getUrl() de root terug: dat is de
            // homepage al, en geen product- of categoriepagina.
            return rtrim($url, '/') === rtrim(Sites::url('/', $siteId), '/') ? null : $url;
        }, null, report: false);
    }
}
