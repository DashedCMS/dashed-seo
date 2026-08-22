<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Fixes\Redirects;

use Dashed\DashedSeo\Fixes\BaseFixer;
use Dashed\DashedCore\Models\Redirect;
use Dashed\DashedSeo\Fixes\FixProposal;
use Dashed\DashedSeo\Models\SiteAuditFix;
use Dashed\DashedSeo\Models\SiteAuditFinding;

/**
 * Een doorverwijzing die bij zichzelf uitkomt maakt de pagina voor niemand
 * bereikbaar. Weghalen is hier de enige zinnige uitkomst; welk adres het wel
 * had moeten zijn kan alleen een mens bepalen.
 */
class RemoveRedirectLoopFixer extends BaseFixer
{
    use FindsRedirect;

    public function checkKeys(): array
    {
        return ['redirect_loop'];
    }

    // Dit verwijdert een rij in dashed__redirects, niet de inhoud van een
    // pagina, dus mag het standaard zonder mens draaien. Dat onderscheidt
    // deze fixer van de linkfixers, die wel in gepubliceerde inhoud
    // schrijven en daarom altijd op voorstel blijven staan.
    public function defaultMode(): string
    {
        return SiteAuditFix::MODE_AUTO;
    }

    public function label(): string
    {
        return __('Doorverwijzing met een lus verwijderen');
    }

    public function propose(SiteAuditFinding $finding): ?FixProposal
    {
        if (! $finding->url) {
            return null;
        }

        $redirect = $this->redirectFor($finding->url);

        if (! $redirect) {
            return null;
        }

        return new FixProposal(
            subject: $redirect,
            locale: null,
            before: ['verwijderd' => false, 'from' => (string) $redirect->from, 'to' => (string) $redirect->to],
            after: ['verwijderd' => true],
            summary: __('De doorverwijzing van :van naar :naar verwijderen, want die komt bij zichzelf uit', [
                'van' => $redirect->from,
                'naar' => $redirect->to,
            ]),
        );
    }

    public function apply(SiteAuditFix $fix): void
    {
        Redirect::find($fix->subject_id)?->delete();
    }

    public function revert(SiteAuditFix $fix): void
    {
        // Niet via $fix->subject: die relatie ziet een zacht verwijderd record
        // niet meer, en dat is nu precies wat er teruggezet moet worden.
        Redirect::withTrashed()->find($fix->subject_id)?->restore();
    }
}
