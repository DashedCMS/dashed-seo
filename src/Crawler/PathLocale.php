<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Crawler;

use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Classes\Locales;

/**
 * De taal van een gecrawld adres, afgeleid uit het pad.
 *
 * LaravelLocalization draait in dit CMS met hideDefaultLocaleInURL, en
 * IsVisitable::getUrl() zet daarom alleen een prefix op een adres wanneer de
 * taal niet de eerste geactiveerde taal is. Het pad draagt de taal dus:
 * /en/over-ons is Engels, /over-ons is de taal zonder prefix.
 *
 * Zonder deze afleiding staat SiteAuditPage.locale op elke rij leeg, en dan is
 * elke taalgarantie in de fixlaag dode letter: een fixer schrijft dan in de
 * taal waar de queue-worker toevallig op staat in plaats van in de taal van de
 * pagina die hij aan het oplossen is.
 */
final class PathLocale
{
    /** @var array<string, array<int, string>> */
    protected static array $cache = [];

    /**
     * De taal van dit pad, of null wanneer deze site geen geactiveerde talen
     * heeft (dan valt er niets te zeggen en is een gok erger dan niets).
     */
    public static function forPath(string $path, string $siteId): ?string
    {
        $talen = self::localesFor($siteId);

        if ($talen === []) {
            return null;
        }

        $segmenten = explode('/', trim(parse_url($path, PHP_URL_PATH) ?: $path, '/'));
        $eerste = strtolower($segmenten[0] ?? '');

        foreach ($talen as $taal) {
            if (strtolower($taal) === $eerste) {
                return $taal;
            }
        }

        return self::defaultFor($siteId);
    }

    /**
     * De geactiveerde talen van deze site, als kale taalcodes in de volgorde
     * die LaravelLocalization aanhoudt.
     *
     * @return array<int, string>
     */
    public static function localesFor(string $siteId): array
    {
        $ruw = null;

        foreach (Sites::getSites() as $site) {
            if (($site['id'] ?? null) == $siteId) {
                $ruw = (array) ($site['locales'] ?? []);

                break;
            }
        }

        if ($ruw === null) {
            return [];
        }

        // De cachesleutel draagt de ruwe talen van de site mee, niet alleen
        // zijn id. Zo levert een site waarvan de talen ondertussen anders
        // staan nooit een antwoord uit de vorige samenstelling op, terwijl de
        // dure kant (Sites::get, die elke taal langs LaravelLocalization
        // sorteert) toch maar een keer per samenstelling gedaan wordt. Deze
        // methode draait per ontdekte link, dus dat verschil telt.
        $sleutel = $siteId . '|' . implode(',', array_map('strval', $ruw));

        if (array_key_exists($sleutel, self::$cache)) {
            return self::$cache[$sleutel];
        }

        $volledig = Sites::get($siteId);

        if (! is_array($volledig) || ! isset($volledig['locales'])) {
            return self::$cache[$sleutel] = [];
        }

        return self::$cache[$sleutel] = collect($volledig['locales'])
            ->pluck('id')
            ->filter(fn ($id): bool => is_string($id) && $id !== '')
            ->values()
            ->all();
    }

    /**
     * De taal die zonder prefix in de url staat. IsVisitable::getUrl()
     * vergelijkt met de eerste geactiveerde taal van de hele installatie, dus
     * dat is de taal die hier hoort. Draait deze site toevallig zonder die
     * taal, dan is zijn eigen eerste taal het beste antwoord dat er is.
     */
    protected static function defaultFor(string $siteId): ?string
    {
        $talen = self::localesFor($siteId);

        if ($talen === []) {
            return null;
        }

        $eersteVanAlles = Locales::getLocales()[0]['id'] ?? null;

        return in_array($eersteVanAlles, $talen, true) ? $eersteVanAlles : $talen[0];
    }

    /**
     * Wist de cache. Nodig tussen twee scans in hetzelfde proces, om dezelfde
     * reden als bij ModelResolver: een queue-worker leeft lang en de
     * site-instellingen kunnen er ondertussen anders uitzien.
     */
    public static function flush(): void
    {
        self::$cache = [];
    }
}
