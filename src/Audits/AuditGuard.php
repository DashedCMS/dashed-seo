<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Audits;

use Illuminate\Support\Facades\DB;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedSeo\Models\SiteAudit;
use Dashed\DashedSeo\Settings\AuditSettings;

/**
 * De enige plek die antwoord geeft op de vraag of een site nu mag scannen.
 * De knop in het scherm, het geplande commando en de startjob stellen alle
 * drie deze vraag, met deze statusverzameling.
 */
final class AuditGuard
{
    /** Null betekent dat het mag. Anders de reden waarom niet, leesbaar. */
    public static function problem(?string $siteId = null): ?string
    {
        $siteId = $siteId ?: Sites::getActive();

        if (AuditSettings::forSite($siteId)->baseUrl === '') {
            return __('De website heeft geen adres (APP_URL), dus er is niets om te scannen.');
        }

        $running = self::running($siteId);

        if ($running) {
            return __('Er loopt al een scan voor deze site, gestart op :tijd.', [
                'tijd' => optional($running->started_at ?? $running->created_at)->format('d-m-Y H:i'),
            ]);
        }

        return null;
    }

    public static function running(?string $siteId = null): ?SiteAudit
    {
        return SiteAudit::query()
            ->where('site_id', $siteId ?: Sites::getActive())
            ->whereIn('status', SiteAudit::runningStatuses())
            ->orderBy('id')
            ->first();
    }

    /**
     * Claimt de site en levert de aangemaakte scan op, of null als het niet
     * mocht. De aanroeper hoeft problem() niet apart te draaien: dit is de
     * bindende beslissing, problem() is er om de reden te kunnen tonen.
     */
    public static function claim(?string $siteId = null, string $trigger = 'manual'): ?SiteAudit
    {
        $siteId = $siteId ?: Sites::getActive();
        $settings = AuditSettings::forSite($siteId);

        if ($settings->baseUrl === '') {
            return null;
        }

        return DB::transaction(function () use ($siteId, $trigger, $settings) {
            $running = SiteAudit::query()
                ->where('site_id', $siteId)
                ->whereIn('status', SiteAudit::runningStatuses())
                ->lockForUpdate()
                ->exists();

            if ($running) {
                return null;
            }

            $audit = SiteAudit::create([
                'site_id' => $siteId,
                'base_url' => $settings->baseUrl,
                'status' => SiteAudit::STATUS_PENDING,
                'trigger' => $trigger,
                'settings' => $settings->toSnapshot(),
            ]);

            // De vergrendeling hierboven sluit alleen bestaande rijen af. Twee
            // processen die tegelijk claimen op een site zonder lopende scan
            // grendelen dus allebei niets en maken allebei een rij aan. Deze
            // tweede controle maakt daar een deterministische uitkomst van:
            // van twee racers trekt degene met het hoogste id zich terug.
            $competitor = SiteAudit::query()
                ->where('site_id', $siteId)
                ->whereIn('status', SiteAudit::runningStatuses())
                ->where('id', '<', $audit->id)
                ->exists();

            if ($competitor) {
                $audit->forceDelete();

                return null;
            }

            return $audit;
        });
    }
}
