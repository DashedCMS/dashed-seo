<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\Speed;

use Dashed\DashedSeo\Checks\Finding;
use Dashed\DashedSeo\Checks\BaseCheck;
use Dashed\DashedSeo\Models\SiteAudit;
use Illuminate\Support\Facades\Schema;
use Dashed\DashedSeo\Crawler\UrlNormalizer;
use Dashed\DashedSeo\Models\PageSpeedMeasurement;

/**
 * Leest de Prestatiemonitor (PageSpeed Insights) in plaats van zelf te meten:
 * de crawler ziet alleen de reactietijd van de server, niet wat een bezoeker
 * op zijn telefoon ervaart. Zonder recente meting (monitor uit, of langer dan
 * MAX_AGE_DAYS geleden) meldt deze controle niets.
 */
class PoorCoreWebVitalsCheck extends BaseCheck
{
    public const MAX_AGE_DAYS = 14;

    public const POOR_SCORE = 50;

    public function key(): string
    {
        return 'poor_core_web_vitals';
    }

    public function run(SiteAudit $audit): iterable
    {
        if (! Schema::hasTable('dashed__pagespeed_measurements')) {
            return;
        }

        $latestIds = PageSpeedMeasurement::query()
            ->where('site_id', (string) $audit->site_id)
            ->whereNull('error')
            ->where('measured_at', '>=', now()->subDays(self::MAX_AGE_DAYS))
            ->groupBy('url', 'strategy')
            ->selectRaw('MAX(id) as id')
            ->pluck('id');

        $perUrl = [];

        foreach (PageSpeedMeasurement::query()->whereIn('id', $latestIds)->orderBy('url')->orderBy('strategy')->get() as $measurement) {
            $poor = $measurement->poorMetrics();

            if ($measurement->performance_score !== null && $measurement->performance_score < self::POOR_SCORE) {
                $poor[] = 'score';
            }

            if (! $poor) {
                continue;
            }

            $perUrl[$measurement->url][$measurement->strategy] = [
                'metrics' => $poor,
                'score' => $measurement->performance_score,
                'lcp_ms' => $measurement->field_lcp_ms ?? $measurement->lab_lcp_ms,
                'inp_ms' => $measurement->field_inp_ms,
                'cls' => $measurement->field_cls ?? $measurement->lab_cls,
                'field' => $measurement->field_lcp_ms !== null,
            ];
        }

        foreach ($perUrl as $url => $strategies) {
            $pageId = $audit->pages()->getQuery()->where('url_hash', UrlNormalizer::hash($url))->value('id');

            yield new Finding($this->key(), $this->section(), $this->severity(), $pageId, $url, ['strategies' => $strategies]);
        }
    }
}
