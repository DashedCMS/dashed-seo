<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Filament\Actions;

use Filament\Actions\Action;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Classes\Locales;
use Illuminate\Database\Eloquent\Model;
use Dashed\DashedSeo\SearchConsole\PagePath;
use Dashed\DashedSeo\SearchConsole\KeywordStats;
use Dashed\DashedSeo\Settings\SearchConsoleSettings;

/**
 * "Zoekwoorden" in het actiemenu van een bewerkscherm: de topzoektermen van
 * deze pagina (alle talen samen) over de laatste 28 dagen, met de periode
 * daarvoor als trend. Core zet hem in het menu achter een class_exists.
 */
class SearchConsoleKeywordsAction
{
    public static function make(): Action
    {
        return Action::make('searchConsoleKeywords')
            ->label(__('Zoekwoorden (Search Console)'))
            ->icon('heroicon-o-magnifying-glass')
            ->visible(fn () => auth()->user()?->can('view_search_performance') && SearchConsoleSettings::enabled((string) Sites::getActive()))
            ->modalHeading(__('Zoekwoorden uit Search Console'))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('Sluiten'))
            ->modalWidth('5xl')
            ->modalContent(function ($livewire) {
                $record = method_exists($livewire, 'getRecord') ? $livewire->getRecord() : null;
                $siteId = (string) Sites::getActive();
                $paths = $record ? self::pathsFor($record) : [];

                return view('dashed-seo::filament.modals.search-console-keywords', [
                    'rows' => KeywordStats::forPaths($siteId, $paths, 28, 25),
                    'daily' => KeywordStats::dailyClicks($siteId, $paths, 28),
                    'latest' => KeywordStats::latestDate($siteId),
                    'paths' => $paths,
                ]);
            });
    }

    /** @return array<int, string> de paden van dit model in elke taal */
    public static function pathsFor(Model $record): array
    {
        $paths = [];

        foreach (Locales::getLocales() as $locale) {
            $url = rescue(fn () => $record->getUrl($locale['id']), null, report: false);

            if (is_string($url) && $url !== '') {
                $paths[] = PagePath::from($url);
            }
        }

        return array_values(array_unique($paths));
    }
}
