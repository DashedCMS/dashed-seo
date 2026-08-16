<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Filament\Support;

use Dashed\DashedSeo\SeoCheckRegistry;
use Dashed\DashedSeo\Models\SiteAudit;

/**
 * Het rapport uit de database halen, los van de weergave. De Blade-bestanden
 * krijgen kale arrays en hoeven zelf niets op te zoeken.
 */
final class ReportData
{
    /** Zwaarder telt eerst bij het sorteren van de grootste problemen. */
    protected const SEVERITY_ORDER = ['error' => 3, 'warning' => 2, 'notice' => 1];

    /** @return array<string, mixed> */
    public static function cover(SiteAudit $audit): array
    {
        $previous = $audit->previous();
        $registry = app(SeoCheckRegistry::class);

        $top = $audit->checkResults()
            ->where('count', '>', 0)
            ->get()
            ->sortByDesc(fn ($result): array => [
                self::SEVERITY_ORDER[$result->severity] ?? 0,
                $result->count,
            ])
            ->take(5)
            ->map(fn ($result): array => [
                'check_key' => $result->check_key,
                'label' => $registry->check($result->check_key)?->label() ?? $result->check_key,
                'severity' => $result->severity,
                'count' => (int) $result->count,
            ])
            ->values()
            ->all();

        return [
            'score' => $audit->health_score,
            'delta' => ($previous && $previous->health_score !== null && $audit->health_score !== null)
                ? (int) $audit->health_score - (int) $previous->health_score
                : null,
            'previous_finished_at' => $previous?->finished_at,
            'errors' => (int) $audit->error_count,
            'warnings' => (int) $audit->warning_count,
            'notices' => (int) $audit->notice_count,
            'pages_crawled' => (int) $audit->pages_crawled,
            'pages_blocked' => (int) $audit->pages_blocked,
            'urls_found' => (int) $audit->urls_found,
            'top' => $top,
            'warnings_list' => self::warnings($audit),
        ];
    }

    /**
     * Meldingen over de scan zelf, niet over de site. Een scan die er geslaagd
     * uitziet terwijl hij bijna niets gezien heeft is de gevaarlijkste
     * uitkomst die dit gereedschap kan geven, dus die zegt hij hier hardop.
     *
     * @return array<int, string>
     */
    protected static function warnings(SiteAudit $audit): array
    {
        $settings = $audit->settings ?? [];
        $warnings = [];

        $rowsSeen = (int) ($settings['sitemap_rows_seen'] ?? 0);
        $usable = (int) ($settings['sitemap_url_count'] ?? 0);

        if ($rowsSeen > 0 && $usable === 0) {
            $warnings[] = __('De sitemap leverde :aantal adressen op, maar geen enkele daarvan hoort bij deze site. Controleer of de sitemap naar de juiste host wijst.', [
                'aantal' => $rowsSeen,
            ]);
        } elseif ($rowsSeen === 0 && ! ($settings['robots_has_sitemap'] ?? false)) {
            $warnings[] = __('Er is geen sitemap gevonden. De scan is bij de startpagina begonnen en heeft de rest via links ontdekt.');
        }

        $configured = rtrim((string) ($settings['base_url_configured'] ?? ''), '/');

        if ($configured !== '' && $configured !== rtrim((string) $audit->base_url, '/')) {
            $warnings[] = __('De ingestelde basis-URL :ingesteld stuurde door naar :werkelijk, en de scan heeft dat laatste adres gebruikt.', [
                'ingesteld' => $configured,
                'werkelijk' => $audit->base_url,
            ]);
        }

        if ($audit->pages_blocked > 0) {
            $warnings[] = __(':aantal adressen zijn door robots.txt afgeschermd en niet opgehaald. Ze tellen niet mee in de aantallen hieronder.', [
                'aantal' => $audit->pages_blocked,
            ]);
        }

        if ((int) $audit->pages_crawled >= (int) ($settings['max_pages'] ?? PHP_INT_MAX)) {
            $warnings[] = __('De paginalimiet van :limiet is bereikt, dus de site is mogelijk niet helemaal gescand.', [
                'limiet' => $settings['max_pages'],
            ]);
        }

        return $warnings;
    }
}
