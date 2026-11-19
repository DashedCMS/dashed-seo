<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\PageSpeed;

use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedSeo\Support\AdminAlert;
use Dashed\DashedSeo\Settings\PageSpeedSettings;
use Dashed\DashedSeo\Models\PageSpeedMeasurement;
use Dashed\DashedSeo\Filament\Pages\PageSpeedMonitorPage;

class PageSpeedMonitor
{
    /** De nieuwe score wordt vergeleken met het gemiddelde van zoveel eerdere metingen. */
    public const BASELINE_MEASUREMENTS = 7;

    public function __construct(protected ?PageSpeedClient $client = null)
    {
    }

    /** @return int aantal opgeslagen metingen */
    public function measureSite(string $siteId): int
    {
        if (! PageSpeedSettings::ready($siteId)) {
            return 0;
        }

        $client = $this->client ?? new PageSpeedClient((string) PageSpeedSettings::apiKey($siteId));
        $regressions = [];
        $stored = 0;

        foreach (PageSpeedUrls::forSite($siteId) as $url) {
            foreach (PageSpeedMeasurement::STRATEGIES as $strategy) {
                $baseline = $this->baseline($siteId, $url, $strategy);

                $measurement = PageSpeedMeasurement::query()->create(array_merge(
                    $client->measure($url, $strategy),
                    ['site_id' => $siteId, 'url' => mb_substr($url, 0, 500), 'strategy' => $strategy, 'measured_at' => now()],
                ));
                $stored++;

                if ($baseline !== null && $measurement->performance_score !== null
                    && $baseline - $measurement->performance_score >= PageSpeedSettings::alertDrop($siteId)) {
                    $regressions[] = __(':url (:strategie): score van gemiddeld :oud naar :nieuw', [
                        'url' => $url,
                        'strategie' => $strategy === PageSpeedMeasurement::STRATEGY_MOBILE ? __('mobiel') : __('desktop'),
                        'oud' => (int) round($baseline),
                        'nieuw' => $measurement->performance_score,
                    ]);
                }
            }
        }

        if ($regressions) {
            AdminAlert::send(
                __('Laadsnelheid verslechterd op :site', ['site' => ucfirst((string) (Sites::get($siteId)['name'] ?? $siteId))]),
                implode("\n", $regressions),
                rescue(fn () => PageSpeedMonitorPage::getUrl(), null, report: false),
                __('Prestatiemonitor bekijken'),
            );
        }

        return $stored;
    }

    /** Gemiddelde score van de laatste geslaagde metingen, of null zonder geschiedenis. */
    protected function baseline(string $siteId, string $url, string $strategy): ?float
    {
        $scores = PageSpeedMeasurement::query()
            ->where('site_id', $siteId)
            ->where('url', mb_substr($url, 0, 500))
            ->where('strategy', $strategy)
            ->whereNotNull('performance_score')
            ->latest('measured_at')
            ->latest('id')
            ->limit(self::BASELINE_MEASUREMENTS)
            ->pluck('performance_score');

        return $scores->isEmpty() ? null : (float) $scores->avg();
    }
}
