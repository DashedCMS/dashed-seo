<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Audits;

use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedSeo\Models\SiteAudit;
use Dashed\DashedSeo\Models\SiteAuditLink;
use Dashed\DashedSeo\Models\SiteAuditPage;
use Dashed\DashedSeo\Models\SiteAuditAsset;
use Dashed\DashedSeo\Models\SiteAuditFinding;

final class AuditPruner
{
    /**
     * Kleedt scans uit die buiten de bewaartermijn vallen: de pagina's, links,
     * bronbestanden en bevindingen gaan weg, de scan zelf en zijn samenvatting
     * per controle blijven staan. Zo blijft de grafiek van de technische score
     * over de tijd intact zonder dat de database volloopt. Geeft terug hoeveel
     * scans zijn uitgekleed.
     *
     * $keep is het aantal volledige scans dat overblijft. Optioneel: zonder
     * expliciete waarde valt hij terug op de config, wat de oude aanroepen
     * (zonder argument) laat blijven werken. `SiteAuditOpruimer` geeft hier
     * altijd de ingestelde `Termijn::waarde()` door, zodat de instelling op
     * het scherm ook echt bepaalt hoeveel scans er blijven staan.
     */
    public static function prune(?int $keep = null): int
    {
        $pruned = 0;

        foreach (Sites::getSites() as $site) {
            self::uitTeKleden($site['id'], $keep)
                ->orderBy('id')
                ->chunkById(50, function ($audits) use (&$pruned): void {
                    foreach ($audits as $audit) {
                        SiteAuditLink::where('audit_id', $audit->id)->delete();
                        SiteAuditFinding::where('audit_id', $audit->id)->delete();
                        SiteAuditAsset::where('audit_id', $audit->id)->delete();
                        SiteAuditPage::where('audit_id', $audit->id)->delete();

                        $pruned++;
                    }
                });
        }

        return $pruned;
    }

    /**
     * De scans die buiten de bewaartermijn vallen en nog uitgekleed moeten
     * worden. Gedeeld door prune() en telUitTeKleden(), zodat een droge run
     * niet uit de pas kan lopen met wat er daadwerkelijk gebeurt.
     */
    private static function uitTeKleden(string $siteId, ?int $keep = null): \Illuminate\Database\Eloquent\Builder
    {
        $keep ??= (int) config('dashed-seo.retention.keep_full_audits');

        $keepIds = SiteAudit::query()
            ->where('site_id', $siteId)
            ->where('status', SiteAudit::STATUS_COMPLETED)
            ->orderByDesc('finished_at')
            ->orderByDesc('id')
            ->limit($keep)
            ->pluck('id');

        return SiteAudit::query()
            ->where('site_id', $siteId)
            ->whereNotIn('id', $keepIds)
            // Een lopende scan staat nooit in de bewaarlijst hierboven (die
            // filtert op STATUS_COMPLETED) maar heeft tijdens het crawlen wel
            // al pagina's. Zonder dit filter trekt het uitkleden de
            // werkvoorraad onder een scan vandaan die nog bezig is.
            ->whereNotIn('status', SiteAudit::runningStatuses())
            // Een scan die al is uitgekleed heeft geen pagina's meer.
            ->whereHas('pages');
    }

    public static function telUitTeKleden(?int $keep = null): int
    {
        $aantal = 0;

        foreach (Sites::getSites() as $site) {
            $aantal += self::uitTeKleden($site['id'], $keep)->count();
        }

        return $aantal;
    }
}
