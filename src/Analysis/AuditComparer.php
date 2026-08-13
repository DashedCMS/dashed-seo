<?php

// packages/dashed/dashed-seo/src/Analysis/AuditComparer.php

declare(strict_types=1);

namespace Dashed\DashedSeo\Analysis;

use Dashed\DashedSeo\Models\SiteAudit;
use Dashed\DashedSeo\Models\SiteAuditFinding;

class AuditComparer
{
    public static function previous(SiteAudit $audit): ?SiteAudit
    {
        return SiteAudit::where('site_id', $audit->site_id)
            ->where('id', '!=', $audit->id)
            ->where('status', SiteAudit::STATUS_COMPLETED)
            ->orderByDesc('finished_at')
            ->orderByDesc('id')
            ->first();
    }

    public static function markNew(SiteAudit $audit): void
    {
        $previous = self::previous($audit);

        if ($previous === null) {
            return;
        }

        $before = self::keys($previous);

        SiteAuditFinding::where('audit_id', $audit->id)
            ->orderBy('id')
            ->chunkById(500, function ($findings) use ($before) {
                foreach ($findings as $finding) {
                    if (! isset($before[self::key($finding->check_key, $finding->url)])) {
                        $finding->update(['is_new' => true]);
                    }
                }
            });
    }

    /**
     * Per controle het aantal verschillende pagina's dat de vorige scan nog
     * wel raakte en deze scan niet meer. Zelfde eenheid als
     * site_audit_check_results.count: een pagina, geen bevindingsregel. Een
     * controle die per link meldt (internal_link_to_4xx) kan twee
     * bevindingsregels op dezelfde pagina achterlaten in de vorige scan; die
     * horen hier als een enkele opgeloste pagina te tellen.
     *
     * @return array<string, int>
     */
    public static function resolvedCounts(SiteAudit $audit): array
    {
        $previous = self::previous($audit);

        if ($previous === null) {
            return [];
        }

        $now = self::keys($audit);
        $counts = [];
        $seen = [];

        foreach (SiteAuditFinding::where('audit_id', $previous->id)->cursor() as $finding) {
            $key = self::key($finding->check_key, $finding->url);

            if (isset($now[$key]) || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $counts[$finding->check_key] = ($counts[$finding->check_key] ?? 0) + 1;
        }

        return $counts;
    }

    /** @return array<string, true> */
    protected static function keys(SiteAudit $audit): array
    {
        $keys = [];

        foreach (SiteAuditFinding::where('audit_id', $audit->id)->cursor() as $finding) {
            $keys[self::key($finding->check_key, $finding->url)] = true;
        }

        return $keys;
    }

    protected static function key(string $checkKey, ?string $url): string
    {
        return $checkKey . '|' . ($url ?? '');
    }
}
