<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Fixes\Redirects;

use Dashed\DashedSeo\Fixes\BaseFixer;
use Dashed\DashedSeo\Fixes\FixProposal;
use Dashed\DashedSeo\Models\SiteAuditFix;
use Dashed\DashedSeo\Models\SiteAuditFinding;

/**
 * Een tijdelijke doorverwijzing die blijvend blijkt te zijn, permanent maken.
 * Alleen mogelijk als de doorverwijzing in dashed__redirects staat.
 */
class PermanentRedirectFixer extends BaseFixer
{
    use FindsRedirect;

    public function checkKeys(): array
    {
        return ['temporary_redirect'];
    }

    // Dit wijzigt een rij in dashed__redirects, niet de inhoud van een
    // pagina, dus mag het standaard zonder mens draaien. Dat onderscheidt
    // deze fixer van de linkfixers, die wel in gepubliceerde inhoud
    // schrijven en daarom altijd op voorstel blijven staan.
    public function defaultMode(): string
    {
        return SiteAuditFix::MODE_AUTO;
    }

    public function label(): string
    {
        return __('Doorverwijzing permanent maken (301)');
    }

    public function propose(SiteAuditFinding $finding): ?FixProposal
    {
        if (! $finding->url) {
            return null;
        }

        $redirect = $this->redirectFor($finding->url);

        if (! $redirect || (string) $redirect->sort === '301') {
            return null;
        }

        return new FixProposal(
            subject: $redirect,
            locale: null,
            before: ['sort' => $redirect->sort],
            after: ['sort' => '301'],
            summary: __('De doorverwijzing van :van naar :naar op 301 zetten', [
                'van' => $redirect->from,
                'naar' => $redirect->to,
            ]),
        );
    }
}
