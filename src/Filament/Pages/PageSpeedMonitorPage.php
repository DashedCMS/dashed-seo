<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Filament\Pages;

use UnitEnum;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Dashed\DashedCore\Classes\Sites;
use Filament\Notifications\Notification;
use Dashed\DashedSeo\Jobs\MeasurePageSpeedJob;
use Dashed\DashedSeo\Settings\PageSpeedSettings;
use Dashed\DashedSeo\Models\PageSpeedMeasurement;
use Dashed\DashedSeo\Filament\Pages\Settings\SearchPerformanceSettingsPage;

/**
 * De Prestatiemonitor: per gemeten URL de laatste score en Core Web Vitals,
 * mobiel en desktop, en het verloop van de score over de laatste 90 dagen.
 */
class PageSpeedMonitorPage extends Page
{
    use \Dashed\DashedCore\Filament\Concerns\TranslatesPageLabels;

    public const DAYS = 90;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-bolt';

    protected static string | UnitEnum | null $navigationGroup = 'SEO & site';

    protected static ?string $navigationLabel = 'Prestatiemonitor';

    protected static ?string $title = 'Prestatiemonitor';

    protected static ?int $navigationSort = 21;

    protected static ?string $slug = 'pagespeed-monitor';

    protected string $view = 'dashed-seo::filament.pages.pagespeed-monitor';

    public ?string $siteId = null;

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->can('view_search_performance');
    }

    public static function shouldRegisterNavigation(): bool
    {
        if (! static::canAccess()) {
            return false;
        }

        foreach (Sites::getSites() as $site) {
            if (PageSpeedSettings::enabled($site['id'])) {
                return true;
            }
        }

        return false;
    }

    public function mount(): void
    {
        $this->siteId = (string) Sites::getActive();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('measureNow')
                ->label(__('Nu meten'))
                ->icon('heroicon-o-play')
                ->visible(fn () => PageSpeedSettings::ready($this->siteId))
                ->action(function (): void {
                    MeasurePageSpeedJob::dispatch((string) $this->siteId);
                    Notification::make()->title(__('De metingen draaien op de achtergrond en staan hier over een paar minuten'))->success()->send();
                }),
            Action::make('settings')
                ->label(__('Instellingen'))
                ->icon('heroicon-o-cog-6-tooth')
                ->color('gray')
                ->url(fn () => SearchPerformanceSettingsPage::getUrl()),
        ];
    }

    protected function getViewData(): array
    {
        $measurements = PageSpeedMeasurement::query()
            ->where('site_id', (string) $this->siteId)
            ->where('measured_at', '>=', now()->subDays(self::DAYS))
            ->orderBy('measured_at')
            ->orderBy('id')
            ->get();

        $urls = [];

        foreach ($measurements->groupBy('url') as $url => $perUrl) {
            $urls[$url] = [
                'latest' => [
                    PageSpeedMeasurement::STRATEGY_MOBILE => $perUrl->where('strategy', PageSpeedMeasurement::STRATEGY_MOBILE)->last(),
                    PageSpeedMeasurement::STRATEGY_DESKTOP => $perUrl->where('strategy', PageSpeedMeasurement::STRATEGY_DESKTOP)->last(),
                ],
                'series' => [
                    PageSpeedMeasurement::STRATEGY_MOBILE => self::series($perUrl->where('strategy', PageSpeedMeasurement::STRATEGY_MOBILE)),
                    PageSpeedMeasurement::STRATEGY_DESKTOP => self::series($perUrl->where('strategy', PageSpeedMeasurement::STRATEGY_DESKTOP)),
                ],
            ];
        }

        return [
            'sites' => Sites::getSites(),
            'enabled' => PageSpeedSettings::enabled((string) $this->siteId),
            'urls' => $urls,
        ];
    }

    /**
     * Punten voor een SVG-lijn van 600 bij 120: x naar tijd binnen de
     * periode, y naar score 0-100.
     */
    public static function series($measurements): string
    {
        $start = now()->subDays(self::DAYS)->getTimestamp();
        $span = max(1, now()->getTimestamp() - $start);

        return $measurements
            ->filter(fn ($m) => $m->performance_score !== null)
            ->map(fn ($m) => round(($m->measured_at->getTimestamp() - $start) / $span * 600, 1) . ',' . round(120 - $m->performance_score * 1.2, 1))
            ->implode(' ');
    }
}
