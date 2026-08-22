<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Fixes\Redirects;

use Dashed\DashedSeo\Fixes\BaseFixer;
use Dashed\DashedSeo\Fixes\FixProposal;
use Dashed\DashedSeo\Models\SiteAuditFix;
use Dashed\DashedSeo\Models\SiteAuditFinding;

/**
 * Een keten van doorverwijzingen platslaan: de eerste wijst voortaan
 * rechtstreeks naar het einddoel.
 */
class FlattenRedirectChainFixer extends BaseFixer
{
    use FindsRedirect;

    public function checkKeys(): array
    {
        return ['long_redirect_chain'];
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
        return __('Doorverwijzing rechtstreeks naar het einddoel laten wijzen');
    }

    public function propose(SiteAuditFinding $finding): ?FixProposal
    {
        $keten = $finding->context['chain'] ?? [];

        if (! is_array($keten) || $keten === [] || ! $finding->url) {
            return null;
        }

        $eind = $this->path((string) end($keten));
        $redirect = $this->redirectFor($finding->url);

        if (! $redirect || $this->path((string) $redirect->to) === $eind) {
            return null;
        }

        return new FixProposal(
            subject: $redirect,
            locale: null,
            before: ['to' => (string) $redirect->to],
            after: ['to' => $eind],
            summary: __('De doorverwijzing van :van rechtstreeks naar :naar laten wijzen', [
                'van' => $redirect->from,
                'naar' => $eind,
            ]),
        );
    }
}
