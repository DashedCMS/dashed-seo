<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\SearchConsole;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Dashed\DashedSeo\Models\SearchConsoleRow;
use Dashed\DashedSeo\Settings\SearchConsoleSettings;

/**
 * Haalt per dag de rijen (pagina x zoekterm) van één site op en vervangt
 * die dag in de tabel. Vervangen in plaats van bijwerken maakt de ronde
 * herhaalbaar: de laatste dagen worden elke run opnieuw opgehaald, want
 * Search Console werkt de cijfers van de afgelopen dagen nog bij.
 */
class SearchConsoleSync
{
    /** Zoveel dagen terug bij de eerste keer ophalen. */
    public const INITIAL_DAYS = 28;

    /** Search Console loopt ongeveer twee dagen achter. */
    public const LAG_DAYS = 2;

    /** Zoveel recente dagen worden elke ronde opnieuw opgehaald. */
    public const REFRESH_DAYS = 3;

    /** Hooguit zoveel dagen per ronde, zodat een lange stilstand de job niet laat vastlopen. */
    public const MAX_DAYS_PER_RUN = 31;

    /** Hooguit zoveel pagina's van 25.000 rijen per dag. */
    public const MAX_PAGES_PER_DAY = 4;

    public function __construct(protected ?SearchConsoleClient $client = null)
    {
    }

    /** @return int aantal opgeslagen rijen */
    public function sync(string $siteId, ?Carbon $today = null): int
    {
        if (! SearchConsoleSettings::ready($siteId)) {
            return 0;
        }

        $client = $this->client ?? new SearchConsoleClient($siteId);
        $property = (string) SearchConsoleSettings::property($siteId);
        $today = ($today ?? now())->copy()->startOfDay();
        $end = $today->copy()->subDays(self::LAG_DAYS);

        $last = SearchConsoleRow::query()->where('site_id', $siteId)->max('date');
        $start = $last
            ? Carbon::parse($last)->startOfDay()->subDays(self::REFRESH_DAYS - 1)
            : $end->copy()->subDays(self::INITIAL_DAYS - 1);

        if ($start->lt($end->copy()->subDays(self::MAX_DAYS_PER_RUN - 1))) {
            $start = $end->copy()->subDays(self::MAX_DAYS_PER_RUN - 1);
        }

        $stored = 0;

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $stored += $this->syncDate($client, $siteId, $property, $date->toDateString());
        }

        return $stored;
    }

    protected function syncDate(SearchConsoleClient $client, string $siteId, string $property, string $date): int
    {
        $rows = [];

        for ($page = 0; $page < self::MAX_PAGES_PER_DAY; $page++) {
            $batch = $client->rowsForDate($property, $date, $page * SearchConsoleClient::ROW_LIMIT);

            foreach ($batch as $row) {
                $url = (string) ($row['keys'][0] ?? '');
                $query = (string) ($row['keys'][1] ?? '');

                if ($url === '' || $query === '') {
                    continue;
                }

                $rows[] = [
                    'site_id' => $siteId,
                    'date' => $date,
                    'page' => $url,
                    'page_path' => PagePath::from($url),
                    'search_query' => mb_substr($query, 0, 500),
                    'clicks' => (int) ($row['clicks'] ?? 0),
                    'impressions' => (int) ($row['impressions'] ?? 0),
                    'ctr' => (float) ($row['ctr'] ?? 0),
                    'position' => (float) ($row['position'] ?? 0),
                ];
            }

            if (count($batch) < SearchConsoleClient::ROW_LIMIT) {
                break;
            }
        }

        // Pas na een geslaagde ophaalronde de oude rijen weg, zodat een
        // fout bij Google geen gat in de cijfers achterlaat.
        DB::transaction(function () use ($siteId, $date, $rows): void {
            SearchConsoleRow::query()->where('site_id', $siteId)->whereDate('date', $date)->delete();

            foreach (array_chunk($rows, 1000) as $chunk) {
                SearchConsoleRow::query()->insert($chunk);
            }
        });

        return count($rows);
    }
}
