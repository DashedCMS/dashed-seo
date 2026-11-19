<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\SearchConsole;

use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\Cache;
use Dashed\DashedSeo\Support\AdminAlert;
use Dashed\DashedSeo\Settings\SearchConsoleSettings;
use Dashed\DashedSeo\Filament\Pages\SearchConsoleKeywordsPage;

/**
 * Meldt na een ophaalronde de dalers die er nog niet eerder in een melding
 * stonden. Elke combinatie van pagina en zoekterm wordt hooguit één keer per
 * twee weken gemeld, anders komt dezelfde lijst elke ochtend opnieuw binnen.
 */
class DropNotifier
{
    public const SILENCE_DAYS = 14;

    public const MAX_IN_MESSAGE = 10;

    /** @return int aantal gemelde dalers */
    public function notify(string $siteId): int
    {
        if (! SearchConsoleSettings::enabled($siteId) || ! SearchConsoleSettings::alertsEnabled($siteId)) {
            return 0;
        }

        $new = KeywordStats::decliners(
            $siteId,
            SearchConsoleSettings::dropThreshold($siteId),
            SearchConsoleSettings::minImpressions($siteId),
        )->filter(fn ($row) => Cache::add(
            'dashed-seo:gsc-drop:' . $siteId . ':' . md5($row->page_path . '|' . $row->search_query),
            true,
            now()->addDays(self::SILENCE_DAYS),
        ))->values();

        if ($new->isEmpty()) {
            return 0;
        }

        $siteName = ucfirst((string) (Sites::get($siteId)['name'] ?? $siteId));

        $lines = $new->take(self::MAX_IN_MESSAGE)->map(fn ($row) => __(':zoekterm op :pagina: van plek :oud naar :nieuw', [
            'zoekterm' => $row->search_query,
            'pagina' => $row->page_path,
            'oud' => $row->prev_position,
            'nieuw' => $row->position,
        ]))->implode("\n");

        if ($new->count() > self::MAX_IN_MESSAGE) {
            $lines .= "\n" . __('En nog :aantal andere.', ['aantal' => $new->count() - self::MAX_IN_MESSAGE]);
        }

        AdminAlert::send(
            __('Zoekposities gedaald op :site', ['site' => $siteName]),
            $lines,
            rescue(fn () => SearchConsoleKeywordsPage::getUrl(), null, report: false),
            __('Zoekwoorden bekijken'),
        );

        return $new->count();
    }
}
