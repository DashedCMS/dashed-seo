<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Fixes\Links;

use Illuminate\Database\Eloquent\Model;
use Dashed\DashedCore\Models\UrlHistory;

/**
 * Zoekt op via de url-historie waar een pagina die op dit pad stond
 * tegenwoordig staat, in plaats van te gokken op basis van gelijkenis tussen
 * adressen. /product-a en /product-b lijken voor negentig procent op elkaar
 * en zijn twee verschillende producten; de url-historie weet het echt, want
 * IsVisitable schrijft daar bij elke slugwijziging in weg.
 */
final class MovedUrlResolver
{
    /**
     * Het huidige adres van de pagina die ooit op dit pad stond, of null.
     *
     * Bewust uit de url-historie en niet uit gelijkenis tussen adressen:
     * /product-a en /product-b lijken voor tachtig procent op elkaar en zijn
     * twee verschillende producten. Weet de historie het niet, dan stellen we
     * niets voor.
     *
     * Een rij matcht op previous_url (het gewone geval: het pad is precies
     * de vorige stand) of op url (de pagina is na de laatst gesynchroniseerde
     * stand nogmaals verplaatst). In beide gevallen wordt het model gevraagd
     * naar zijn huidige adres via getUrl(), niet de opgeslagen kolom: die kan
     * inmiddels alweer achterhaald zijn.
     *
     * Zonder $locale valt het taalfilter hieronder helemaal weg, want de
     * aanroeper kent de taal niet altijd. Dat betekent dat een kapotte
     * Nederlandse link dan best kan oplossen naar de Engelse versie van een
     * pagina, als die toevallig de nieuwste matchende rij is. Bewust zo
     * gelaten; wie de taal weet, geeft hem mee.
     */
    public static function currentUrlFor(string $pad, string $siteId, ?string $locale = null): ?string
    {
        $pad = self::normalise($pad);

        $query = UrlHistory::query()
            ->where('site_id', $siteId)
            ->where(function ($query) use ($pad) {
                $query->where('previous_url', $pad)
                    ->orWhere('url', $pad);
            });

        if ($locale !== null) {
            $query->where('locale', $locale);
        }

        // Twee verschillende modellen kunnen via previous_url of url allebei
        // op hetzelfde pad matchen, bijvoorbeeld wanneer de ene pagina van
        // /foo wegverhuisde en een andere pagina later /foo als eigen adres
        // kreeg. Zonder een vaste volgorde bepaalt de opslagvolgorde in de
        // database wie er wint; de nieuwste rij (het hoogste id) is de meest
        // recente stand van zaken.
        $historie = $query->orderByDesc('id')->first();

        if (! $historie || ! $historie->model_type || ! $historie->model_id || ! class_exists($historie->model_type)) {
            return null;
        }

        $model = self::resolveVisible($historie->model_type, $historie->model_id);

        if (! $model || ! method_exists($model, 'getUrl')) {
            return null;
        }

        return $model->getUrl($locale ?? $historie->locale);
    }

    /**
     * Het model, maar alleen als het publiek zichtbaar is. Een pagina die
     * offline staat is voor een losse relink hooguit onhandig, maar voor een
     * site-brede 301 een echt probleem: dan stuur je bezoekers structureel
     * naar een adres dat niemand te zien krijgt. Dezelfde scope als
     * Dashed\DashedCore\Classes\Sitemap::create() gebruikt om alleen
     * zichtbare content mee te nemen. Niet elk gekoppeld model heeft die
     * scope (IsVisitable is niet overal gebruikt), dus alleen toepassen als
     * hij bestaat.
     */
    protected static function resolveVisible(string $type, mixed $id): ?Model
    {
        $query = $type::query();

        if (method_exists($type, 'scopePublicShowable')) {
            $query->publicShowable();
        }

        return $query->find($id);
    }

    /** Zelfde normalisatie als BaseFixer::path(): een schuine streep ervoor, geen erachter. */
    protected static function normalise(string $url): string
    {
        $pad = parse_url($url, PHP_URL_PATH) ?: '/';

        return $pad === '/' ? '/' : '/' . trim($pad, '/');
    }
}
