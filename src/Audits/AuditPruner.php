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
     */
    public static function prune(): int
    {
        $keep = (int) config('dashed-seo.retention.keep_full_audits');
        $pruned = 0;

        foreach (Sites::getSites() as $site) {
            $keepIds = SiteAudit::query()
                ->where('site_id', $site['id'])
                ->where('status', SiteAudit::STATUS_COMPLETED)
                ->orderByDesc('finished_at')
                ->orderByDesc('id')
                ->limit($keep)
                ->pluck('id');

            SiteAudit::query()
                ->where('site_id', $site['id'])
                ->whereNotIn('id', $keepIds)
                // Een lopende scan staat nooit in de bewaarlijst hierboven
                // (die filtert op STATUS_COMPLETED) maar heeft tijdens het
                // crawlen wel al pagina's. Zonder dit filter trekt het
                // wekelijkse uitkleden de werkvoorraad onder een scan vandaan
                // die nog bezig is.
                ->whereNotIn('status', SiteAudit::runningStatuses())
                // Een scan die al is uitgekleed heeft geen pagina's meer, dus
                // die hoeft niet elke week opnieuw langs.
                ->whereHas('pages')
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
}
