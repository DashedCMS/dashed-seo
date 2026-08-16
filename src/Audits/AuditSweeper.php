<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Audits;

use Dashed\DashedSeo\Models\SiteAudit;
use Dashed\DashedSeo\Settings\AuditSettings;

final class AuditSweeper
{
    /**
     * Zet scans zonder hartslag op mislukt en geeft terug hoeveel er zijn
     * geveegd. Zonder dit blijft een site voorgoed op slot na een worker die
     * halverwege is gestopt: de guard ziet een lopende scan die nooit meer
     * verder komt.
     */
    public static function sweep(): int
    {
        $swept = 0;

        SiteAudit::query()
            ->whereIn('status', SiteAudit::runningStatuses())
            ->orderBy('id')
            ->chunkById(100, function ($audits) use (&$swept): void {
                foreach ($audits as $audit) {
                    // De drempel hoort bij de site, niet bij de scan: een scan
                    // die al liep voordat iemand de drempel verlaagde moet
                    // gewoon de nieuwe drempel volgen.
                    $minutes = AuditSettings::forSite($audit->site_id)->staleAfterMinutes;

                    $last = $audit->heartbeat_at ?? $audit->created_at;

                    if ($last && $last->diffInMinutes(now()) < $minutes) {
                        continue;
                    }

                    $audit->update([
                        'status' => SiteAudit::STATUS_FAILED,
                        'failure_reason' => __('De scan gaf langer dan :minuten minuten geen hartslag en is afgebroken.', [
                            'minuten' => $minutes,
                        ]),
                        'finished_at' => now(),
                    ]);

                    $swept++;
                }
            });

        return $swept;
    }
}
