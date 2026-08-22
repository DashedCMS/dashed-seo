<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Fixes\Sitemap;

use Dashed\DashedSeo\Fixes\BaseFixer;
use Dashed\DashedCore\Classes\Sitemap;
use Dashed\DashedSeo\Fixes\FixProposal;
use Dashed\DashedSeo\Models\SiteAuditFix;
use Dashed\DashedSeo\Models\SiteAuditFinding;

/**
 * Vijf sitemapcontroles hebben dezelfde oorzaak en dezelfde oplossing: het
 * bestand is verouderd. Sitemap::create() bouwt uit publicShowable() en slaat
 * noindex al over, dus een herbouw haalt de doorverwijzingen, de 404's en de
 * noindex-pagina's er in een keer uit en zet de ontbrekende erbij.
 */
class RebuildSitemapFixer extends BaseFixer
{
    public function isSiteWide(): bool
    {
        return true;
    }

    public function checkKeys(): array
    {
        return [
            'sitemap_redirect',
            'sitemap_noindex',
            'sitemap_4xx',
            'missing_from_sitemap',
            'only_found_via_cms',
        ];
    }

    public function scope(): string
    {
        return self::SCOPE_SITE;
    }

    public function defaultMode(): string
    {
        return SiteAuditFix::MODE_AUTO;
    }

    public function supportsRevert(): bool
    {
        // Er is geen oude sitemap om naar terug te gaan, en een half
        // teruggezet bestand is erger dan geen knop.
        return false;
    }

    public function label(): string
    {
        return __('Sitemap opnieuw opbouwen');
    }

    public function propose(SiteAuditFinding $finding): ?FixProposal
    {
        $bestand = public_path('sitemap.xml');

        // De wijzigingsdatum is hier de zinnige oude waarde: zolang het
        // bestand hetzelfde blijft is het hetzelfde probleem, en een
        // afgewezen voorstel hoort dan niet elke nacht terug te komen.
        $gewijzigd = file_exists($bestand) ? date('c', (int) filemtime($bestand)) : null;

        return new FixProposal(
            subject: null,
            locale: null,
            before: ['sitemap_gewijzigd_op' => $gewijzigd],
            after: ['sitemap_gewijzigd_op' => 'opnieuw opgebouwd'],
            summary: __('De sitemap opnieuw opbouwen uit de zichtbare pagina\'s van het CMS'),
        );
    }

    public function apply(SiteAuditFix $fix): void
    {
        Sitemap::create();
    }

    public function revert(SiteAuditFix $fix): void
    {
        // Wordt nooit aangeroepen: supportsRevert() is false en de jobs
        // controleren dat voordat ze hier uitkomen.
    }
}
