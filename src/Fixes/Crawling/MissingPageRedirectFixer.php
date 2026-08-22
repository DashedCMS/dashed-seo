<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Fixes\Crawling;

use Dashed\DashedSeo\Fixes\BaseFixer;
use Dashed\DashedCore\Models\Redirect;
use Dashed\DashedSeo\Fixes\FixProposal;
use Dashed\DashedSeo\Models\SiteAuditFix;
use Dashed\DashedSeo\Models\SiteAuditFinding;
use Dashed\DashedSeo\Fixes\Links\MovedUrlResolver;
use Dashed\DashedSeo\Fixes\Redirects\FindsRedirect;
use Dashed\DashedSeo\Exceptions\FixStaleException;

/**
 * Een adres dat een 4XX geeft maar waar wel naartoe gelinkt wordt, opvangen
 * met een 301 naar het adres waar dat pad volgens de url-historie
 * tegenwoordig op uitkomt.
 *
 * Bewust via MovedUrlResolver en niet via gelijkenis tussen adressen:
 * /product-a en /product-b lijken voor negentig procent op elkaar en zijn
 * twee verschillende producten. Een site-brede 301 op basis van gelijkenis
 * stuurt bezoekers structureel naar de verkeerde pagina; weet de historie het
 * niet, dan stellen we hier niets voor.
 *
 * De enige fixer die een record aanmaakt in plaats van wijzigt. Daardoor is er
 * bij het voorstellen nog geen subject en blijft before leeg. De gewone
 * verlooptoets van FixGuard kan daar niets mee, dus doet apply() die toets
 * zelf: bestaat er inmiddels al een doorverwijzing vanaf dat adres, dan is dit
 * voorstel achterhaald en gooit hij een FixStaleException.
 */
class MissingPageRedirectFixer extends BaseFixer
{
    // Dezelfde drie padvarianten als elke andere plek die een redirect
    // opzoekt. Zonder dat zouden propose() en de rest van de fixlaag het
    // oneens zijn over de vraag of er al een redirect bestaat.
    use FindsRedirect;

    public function checkKeys(): array
    {
        return ['status_4xx'];
    }

    public function label(): string
    {
        return __('Doorverwijzing maken naar een bestaand adres');
    }

    public function createsModel(): ?string
    {
        return Redirect::class;
    }

    public function propose(SiteAuditFinding $finding): ?FixProposal
    {
        if (! $finding->url) {
            return null;
        }

        $van = $this->path($finding->url);

        if ($this->redirectFor($finding->url)) {
            return null;
        }

        $siteId = $this->siteId($finding);

        if (! $siteId) {
            return null;
        }

        $naar = MovedUrlResolver::currentUrlFor($van, $siteId, $this->localeFor($finding));

        if ($naar === null || $this->path($naar) === $van) {
            return null;
        }

        return new FixProposal(
            subject: null,
            locale: null,
            before: ['redirect' => null],
            after: ['from' => $van, 'to' => $this->path($naar), 'sort' => '301'],
            summary: __('Een 301 maken van :van naar :naar', ['van' => $van, 'naar' => $this->path($naar)]),
        );
    }

    public function apply(SiteAuditFix $fix): void
    {
        $van = (string) ($fix->after['from'] ?? '');

        // De verlooptoets van FixGuard kan hier niets: zonder subject valt hij
        // terug op de vastgelegde oude waarde en klopt de vingerafdruk altijd.
        // Dus kijken we hier zelf nog een keer. Zonder dit overschaduwt een
        // goedgekeurd nachtelijk voorstel stilzwijgend de redirect die de
        // beheerder er 's ochtends zelf bij zette: twee rijen met dezelfde
        // from, en de frontend pakt er een op databasevolgorde.
        if ($van === '' || $this->redirectFor($van)) {
            throw new FixStaleException(__('Er bestaat inmiddels al een doorverwijzing vanaf :van, dus dit voorstel is achterhaald.', ['van' => $van]));
        }

        $redirect = Redirect::create([
            'from' => $fix->after['from'] ?? '',
            'to' => $fix->after['to'] ?? '',
            'sort' => $fix->after['sort'] ?? '301',
        ]);

        // Nu pas bekend, en nodig om hem straks te kunnen terugdraaien.
        $fix->update([
            'subject_type' => $redirect->getMorphClass(),
            'subject_id' => (string) $redirect->id,
        ]);
    }

    public function revert(SiteAuditFix $fix): void
    {
        Redirect::withTrashed()->find($fix->subject_id)?->forceDelete();
    }
}
