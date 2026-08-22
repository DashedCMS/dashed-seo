<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Fixes\Content;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use RalphJSmit\Filament\MediaLibrary\Models\MediaLibraryItem;

/**
 * Van een afbeeldingsadres uit de gecrawlde pagina terug naar het item in de
 * mediabibliotheek. Twee wegen, want de eerste hangt aan de padopbouw van
 * spatie's padgenerator en die is per installatie in te stellen.
 *
 * Spatie's standaardpadgenerator zet elk bestand in een map met het media-id
 * als naam. Deze monorepo gebruikt echter een eigen padgenerator
 * (Dashed\DashedFiles\Services\MediaLibrary\DashedPathGenerator, ingesteld
 * via config/media-library.php), die elk bestand in een map met het
 * media-uuid zet: /{uuid}/foto.jpg, en voor een conversie
 * /{uuid}/conversions/foto-medium.jpg. Een numeriek media-id staat dus
 * helemaal niet in het pad; alleen een uuid. Spatie's eigen
 * Media::findByUuid() (uit HasUuid) vindt het record op basis daarvan.
 */
final class MediaItemLocator
{
    protected const UUID_PATTERN = '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i';

    public static function forSrc(string $src): ?MediaLibraryItem
    {
        $pad = parse_url($src, PHP_URL_PATH) ?: '';

        if ($pad === '' || $pad === '/') {
            return null;
        }

        return self::viaUuid($pad) ?? self::viaBestandsnaam($pad);
    }

    /** Elk bestand staat in een map met het media-uuid als naam. */
    protected static function viaUuid(string $pad): ?MediaLibraryItem
    {
        if (! preg_match(self::UUID_PATTERN, $pad, $match)) {
            return null;
        }

        $media = Media::findByUuid($match[0]);

        return $media?->model instanceof MediaLibraryItem ? $media->model : null;
    }

    /**
     * Terugval op de bestandsnaam. Een conversie heet anders dan het origineel
     * (bijvoorbeeld foto-medium.jpg), dus die achtervoegsels gaan er eerst af.
     *
     * basename() haalt geen percent-encoding eraf, en file_name in de
     * database staat er onvercodeerd in. Op een echte rij is bijvoorbeeld
     * file_name "Japandi%20Set%20-%20Mokka.png" (zelf al een keer
     * geëncodeerd) en de url daarvan "...%2520Set%2520-%2520Mokka.png"
     * (dus dubbel geëncodeerd door de url-generator). Zonder decoderen
     * matcht geen enkele bestandsnaam met een spatie erin, en dat is de
     * normale vorm in deze CMS. We decoderen daarom net zo lang tot
     * decoderen niets meer verandert, en proberen elke tussenvorm inclusief
     * de rauwe, zodat een file_name die toevallig letterlijk %20 bevat ook
     * nog matcht.
     */
    protected static function viaBestandsnaam(string $pad): ?MediaLibraryItem
    {
        $bestand = basename($pad);

        if ($bestand === '') {
            return null;
        }

        $kandidaten = self::decodeerVarianten($bestand);

        $media = Media::whereIn('file_name', $kandidaten)->first();

        if (! $media) {
            foreach ($kandidaten as $kandidaat) {
                $zonderExtensie = pathinfo($kandidaat, PATHINFO_FILENAME);
                $media = Media::whereRaw(
                    "file_name like ? escape '!'",
                    [self::likePatroon($zonderExtensie) . '%'],
                )->first();

                if ($media) {
                    break;
                }
            }
        }

        return $media?->model instanceof MediaLibraryItem ? $media->model : null;
    }

    /**
     * De jokertekens van LIKE onschadelijk maken. Een underscore staat in
     * bestandsnamen voortdurend, en in LIKE betekent die "een willekeurig
     * teken": zonder dit vindt foo_bar ook fooXbar, en dan krijgt een ander
     * bestand in de mediabibliotheek van een klant de alt-tekst.
     *
     * Een uitroepteken als ontsnappingsteken en niet de backslash: SQLite
     * verwerkt geen backslash-ontsnappingen in stringliteralen, dus
     * "escape '\'" is daar twee tekens en dat is geen geldig
     * ontsnappingsteken. Het teken staat als letterlijke tekst in de query en
     * komt niet uit invoer, dus daar is niets aan te injecteren.
     */
    protected static function likePatroon(string $tekst): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $tekst);
    }

    /**
     * Alle vormen van $bestand tussen de rauwe url-vorm en volledig
     * gedecodeerd, inclusief de tussenstappen. Een cap van vijf decodeerslagen
     * voorkomt een oneindige lus bij een pathologische invoer; in de praktijk
     * stopt het na een of twee slagen omdat rawurldecode() dan niets meer
     * verandert.
     *
     * @return array<int, string>
     */
    protected static function decodeerVarianten(string $bestand): array
    {
        $varianten = [$bestand];
        $huidig = $bestand;

        for ($i = 0; $i < 5; $i++) {
            $gedecodeerd = rawurldecode($huidig);

            if ($gedecodeerd === $huidig) {
                break;
            }

            $varianten[] = $gedecodeerd;
            $huidig = $gedecodeerd;
        }

        return array_values(array_unique($varianten));
    }
}
