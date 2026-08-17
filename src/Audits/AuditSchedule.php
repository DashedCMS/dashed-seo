<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Audits;

use Dashed\DashedSeo\Models\SiteAudit;
use Dashed\DashedSeo\Settings\AuditSettings;

/**
 * Bepaalt of een site nu aan een geplande scan toe is. Het commando draait elke
 * nacht; deze klasse zegt per site of dat vandaag ook een scan hoort te worden.
 */
final class AuditSchedule
{
    public static function isDue(string $siteId, ?AuditSettings $settings = null): bool
    {
        $settings ??= AuditSettings::forSite($siteId);

        if ($settings->frequency === AuditSettings::FREQUENCY_NEVER) {
            return false;
        }

        $last = SiteAudit::query()
            ->where('site_id', $siteId)
            ->latest('id')
            ->value('created_at');

        if ($last === null) {
            return true;
        }

        // Een marge van vier uur, want de planner draait op een vast tijdstip.
        // Zonder die marge zou een scan die vorige week een paar minuten later
        // begon deze week een dag overgeslagen worden, en schuift het moment
        // elke ronde verder op.
        return match ($settings->frequency) {
            AuditSettings::FREQUENCY_DAILY => $last->lt(now()->subHours(20)),
            AuditSettings::FREQUENCY_WEEKLY => $last->lt(now()->subDays(7)->addHours(4)),
            AuditSettings::FREQUENCY_MONTHLY => $last->lt(now()->subMonth()->addHours(4)),
            default => false,
        };
    }
}
