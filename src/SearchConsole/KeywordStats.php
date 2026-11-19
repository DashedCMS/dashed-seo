<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\SearchConsole;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Dashed\DashedSeo\Models\SearchConsoleRow;

/**
 * Alle rekensommen op de Search Console-rijen, als aggregaat in de database.
 *
 * Positie is altijd gewogen naar vertoningen (som van positie maal
 * vertoningen gedeeld door de vertoningen), zoals Search Console zelf ook
 * middelt: een dag met tien vertoningen op plek 3 telt zwaarder dan een dag
 * met één vertoning op plek 40.
 */
class KeywordStats
{
    public static function latestDate(string $siteId): ?Carbon
    {
        $max = SearchConsoleRow::query()->where('site_id', $siteId)->max('date');

        return $max ? Carbon::parse($max)->startOfDay() : null;
    }

    /**
     * Topzoektermen van één of meer paden over de laatste $days dagen, met de
     * periode daarvoor als vergelijking.
     *
     * @param  array<int, string>|null  $paths  null = de hele site
     * @return Collection<int, object{search_query: string, clicks: int, impressions: int, ctr: float, position: ?float, prev_clicks: int, prev_impressions: int, prev_position: ?float, position_change: ?float}>
     */
    public static function forPaths(string $siteId, ?array $paths, int $days = 28, int $limit = 25): Collection
    {
        $paths = $paths === null ? null : array_values(array_unique(array_filter($paths)));
        $latest = self::latestDate($siteId);

        if ($paths === [] || ! $latest) {
            return collect();
        }

        $currentStart = $latest->copy()->subDays($days - 1)->toDateString();
        $previousStart = $latest->copy()->subDays(2 * $days - 1)->toDateString();

        $grouped = SearchConsoleRow::query()
            ->where('site_id', $siteId)
            ->when($paths !== null, fn ($query) => $query->whereIn('page_path', $paths))
            ->where('date', '>=', $previousStart)
            ->where('date', '<=', $latest->toDateString())
            ->groupBy('search_query')
            ->select('search_query');

        self::addWindowColumns($grouped, $currentStart);

        return DB::query()
            ->fromSub($grouped, 't')
            ->where('cur_impressions', '>', 0)
            ->orderByDesc('cur_clicks')
            ->orderByDesc('cur_impressions')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => self::present($row));
    }

    /**
     * Dalers: combinaties van pagina en zoekterm waarvan de gewogen positie in
     * de laatste $recentDays dagen minstens $threshold plaatsen slechter is
     * dan in de $baselineDays dagen daarvoor.
     *
     * @return Collection<int, object>
     */
    public static function decliners(string $siteId, float $threshold, int $minImpressions, int $limit = 100, int $recentDays = 7, int $baselineDays = 28): Collection
    {
        $latest = self::latestDate($siteId);

        if (! $latest) {
            return collect();
        }

        $currentStart = $latest->copy()->subDays($recentDays - 1)->toDateString();
        $previousStart = $latest->copy()->subDays($recentDays + $baselineDays - 1)->toDateString();

        $grouped = SearchConsoleRow::query()
            ->where('site_id', $siteId)
            ->where('date', '>=', $previousStart)
            ->where('date', '<=', $latest->toDateString())
            ->groupBy('page_path', 'search_query')
            ->select('page_path', 'search_query')
            ->selectRaw('MAX(page) as page');

        self::addWindowColumns($grouped, $currentStart);

        return DB::query()
            ->fromSub($grouped, 't')
            ->where('cur_impressions', '>=', max(1, $minImpressions))
            ->where('prev_impressions', '>', 0)
            // "? + 0.0": PDO bindt een float in SQLite als tekst, en een getal
            // is daar altijd kleiner dan tekst. Zo wordt het weer een getal.
            ->whereRaw('(cur_position_weighted / cur_impressions) - (prev_position_weighted / prev_impressions) >= (? + 0.0)', [$threshold])
            ->orderByRaw('(cur_position_weighted / cur_impressions) - (prev_position_weighted / prev_impressions) desc')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => self::present($row));
    }

    /**
     * Klikken per dag over de laatste $days dagen, voor de trendlijn.
     *
     * @param  array<int, string>  $paths
     * @return array<string, int> datum => klikken
     */
    public static function dailyClicks(string $siteId, array $paths, int $days = 28): array
    {
        $latest = self::latestDate($siteId);

        if (! $paths || ! $latest) {
            return [];
        }

        $rows = SearchConsoleRow::query()
            ->where('site_id', $siteId)
            ->whereIn('page_path', $paths)
            ->where('date', '>=', $latest->copy()->subDays($days - 1)->toDateString())
            ->groupBy('date')
            ->selectRaw('date, SUM(clicks) as clicks')
            ->pluck('clicks', 'date');

        $result = [];

        for ($date = $latest->copy()->subDays($days - 1); $date->lte($latest); $date->addDay()) {
            $key = $date->toDateString();
            $result[$key] = (int) ($rows[$key] ?? $rows[$key . ' 00:00:00'] ?? 0);
        }

        return $result;
    }

    protected static function addWindowColumns($query, string $currentStart): void
    {
        $query
            ->selectRaw('SUM(CASE WHEN date >= ? THEN clicks ELSE 0 END) as cur_clicks', [$currentStart])
            ->selectRaw('SUM(CASE WHEN date >= ? THEN impressions ELSE 0 END) as cur_impressions', [$currentStart])
            ->selectRaw('SUM(CASE WHEN date >= ? THEN position * impressions ELSE 0 END) as cur_position_weighted', [$currentStart])
            ->selectRaw('SUM(CASE WHEN date < ? THEN clicks ELSE 0 END) as prev_clicks', [$currentStart])
            ->selectRaw('SUM(CASE WHEN date < ? THEN impressions ELSE 0 END) as prev_impressions', [$currentStart])
            ->selectRaw('SUM(CASE WHEN date < ? THEN position * impressions ELSE 0 END) as prev_position_weighted', [$currentStart]);
    }

    protected static function present(object $row): object
    {
        $curImpr = (int) $row->cur_impressions;
        $prevImpr = (int) $row->prev_impressions;
        $position = $curImpr > 0 ? round((float) $row->cur_position_weighted / $curImpr, 1) : null;
        $prevPosition = $prevImpr > 0 ? round((float) $row->prev_position_weighted / $prevImpr, 1) : null;

        return (object) [
            'page' => $row->page ?? null,
            'page_path' => $row->page_path ?? null,
            'search_query' => $row->search_query,
            'clicks' => (int) $row->cur_clicks,
            'impressions' => $curImpr,
            'ctr' => $curImpr > 0 ? round((int) $row->cur_clicks / $curImpr * 100, 1) : 0.0,
            'position' => $position,
            'prev_clicks' => (int) $row->prev_clicks,
            'prev_impressions' => $prevImpr,
            'prev_position' => $prevPosition,
            // Positief = slechter (verder van plek 1).
            'position_change' => $position !== null && $prevPosition !== null ? round($position - $prevPosition, 1) : null,
        ];
    }
}
