<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Filament\Pages;

use UnitEnum;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Dashed\DashedCore\Classes\Sites;
use Filament\Notifications\Notification;
use Dashed\DashedSeo\SearchConsole\KeywordStats;
use Dashed\DashedSeo\Jobs\SyncSearchConsoleJob;
use Dashed\DashedSeo\Settings\SearchConsoleSettings;
use Dashed\DashedSeo\Filament\Pages\Settings\SearchPerformanceSettingsPage;

/**
 * Overzicht van de zoektermen uit Search Console: de dalers bovenaan, de
 * best presterende zoektermen van de site eronder.
 */
class SearchConsoleKeywordsPage extends Page
{
    use \Dashed\DashedCore\Filament\Concerns\TranslatesPageLabels;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-magnifying-glass';

    protected static string | UnitEnum | null $navigationGroup = 'SEO & site';

    protected static ?string $navigationLabel = 'Zoekwoorden';

    protected static ?string $title = 'Zoekwoorden uit Search Console';

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'search-console-keywords';

    protected string $view = 'dashed-seo::filament.pages.search-console-keywords';

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
            if (SearchConsoleSettings::enabled($site['id'])) {
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
            Action::make('syncNow')
                ->label(__('Nu ophalen'))
                ->icon('heroicon-o-arrow-path')
                ->visible(fn () => SearchConsoleSettings::ready($this->siteId))
                ->action(function (): void {
                    SyncSearchConsoleJob::dispatch((string) $this->siteId);
                    Notification::make()->title(__('Search Console wordt op de achtergrond opgehaald'))->success()->send();
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
        $siteId = (string) $this->siteId;
        $latest = KeywordStats::latestDate($siteId);

        return [
            'sites' => Sites::getSites(),
            'ready' => SearchConsoleSettings::ready($siteId),
            'latest' => $latest,
            'threshold' => SearchConsoleSettings::dropThreshold($siteId),
            'decliners' => KeywordStats::decliners($siteId, SearchConsoleSettings::dropThreshold($siteId), SearchConsoleSettings::minImpressions($siteId), 50),
            'topQueries' => KeywordStats::forPaths($siteId, null, 28, 50),
        ];
    }
}
