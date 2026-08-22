<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Filament\Support;

use Dashed\DashedSeo\Models\SiteAudit;
use Dashed\DashedSeo\SeoCheckRegistry;
use Dashed\DashedSeo\Audits\PageEditLink;
use Dashed\DashedSeo\Models\SiteAuditFix;

/**
 * Het rapport uit de database halen, los van de weergave. De Blade-bestanden
 * krijgen kale arrays en hoeven zelf niets op te zoeken.
 */
final class ReportData
{
    /** Zwaarder telt eerst bij het sorteren van de grootste problemen. */
    protected const SEVERITY_ORDER = ['error' => 3, 'warning' => 2, 'notice' => 1];

    /**
     * Hoeveel adressen per controle in de PDF komen. Zonder grens draaide een
     * scan met duizenden bevindingen alles in een keer in het geheugen: ruim
     * honderdzestig megabyte aan rapportgegevens voordat de PDF-bouwer ook maar
     * begon, terwijl de queue-worker op honderdachtentwintig staat. Het aantal
     * dat wegvalt staat onder de lijst, dus een afgekapte lijst blijft eerlijk.
     */
    public const PDF_FINDINGS_PER_CHECK = 100;

    /**
     * De negen secties in de vaste volgorde van het rapport. De volgorde komt
     * hier vandaan en niet uit de database, want anders bepaalt de toevallige
     * registratievolgorde van de controles hoe het rapport eruitziet.
     */
    public const SECTIONS = [
        'crawling' => 'Crawlen en indexatie',
        'redirects' => 'Doorverwijzingen',
        'sitemap' => 'Sitemap',
        'meta_tags' => 'Meta tags',
        'content' => 'Inhoud',
        'localisation' => 'Talen',
        'speed' => 'Snelheid',
        'assets' => 'CSS en JavaScript',
        'links' => 'Links',
    ];

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
     * $withFixes staat uit voor de PDF: de PDF-opmaak leest de sleutels `fix`,
     * `check_key` en `pending_fixes` nergens, dus zonder deze schakelaar
     * betaalt een export met N controles tot twee extra queries per controle
     * voor een resultaat dat meteen weggegooid wordt.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function sections(SiteAudit $audit, bool $withFixes = true): array
    {
        $registry = app(SeoCheckRegistry::class);
        $results = $audit->checkResults()->get()->groupBy('section');

        $sections = [];

        foreach (self::SECTIONS as $key => $label) {
            $checks = ($results[$key] ?? collect())
                // Een controle die niets vond hoort niet in het rapport: het
                // voorbeeldrapport toont ook alleen wat er aan de hand is.
                ->filter(fn ($result): bool => (int) $result->count > 0)
                ->sortByDesc(fn ($result): array => [
                    self::SEVERITY_ORDER[$result->severity] ?? 0,
                    $result->count,
                ])
                ->map(fn ($result): array => [
                    'check_key' => $result->check_key,
                    'label' => $registry->check($result->check_key)?->label() ?? $result->check_key,
                    'severity' => $result->severity,
                    'count' => (int) $result->count,
                    'new_count' => (int) $result->new_count,
                    'resolved_count' => (int) $result->resolved_count,
                    'pending_fixes' => $withFixes ? (int) SiteAuditFix::where('audit_id', $audit->id)
                        ->where('check_key', $result->check_key)
                        ->where('status', SiteAuditFix::STATUS_PENDING)
                        ->count() : 0,
                ])
                ->values()
                ->all();

            $sections[] = [
                'key' => $key,
                'label' => __($label),
                'checks' => $checks,
                'total' => array_sum(array_column($checks, 'count')),
            ];
        }

        return $sections;
    }

    /**
     * Het hele rapport in een enkele array: het voorblad, de secties waarin
     * iets gevonden is, en bij elke controle alle getroffen adressen. Dit
     * voedt de PDF, die bewust niets afkapt: het scherm doet dat wel, dus
     * zonder deze uitdraai is er geen plek meer waar het volledige beeld
     * staat.
     *
     * @return array<string, mixed>
     */
    public static function full(SiteAudit $audit): array
    {
        $sections = [];

        foreach (self::sections($audit, false) as $section) {
            if ($section['checks'] === []) {
                continue;
            }

            $section['checks'] = array_map(
                // false, false: geen bewerklinks en geen fixstatus. De PDF
                // leest geen van beide, en op honderden bevindingen per
                // controle is dat anders pure overhead voor niets.
                fn (array $check): array => $check + [
                    'findings' => self::findings($audit, $check['check_key'], self::PDF_FINDINGS_PER_CHECK, false, false),
                ],
                $section['checks'],
            );

            $sections[] = $section;
        }

        return [
            'cover' => self::cover($audit),
            'sections' => $sections,
        ];
    }

    /**
     * De getroffen adressen van een enkele controle, met de uitleg erbij. Een
     * limiet van null haalt alles op.
     *
     * $withEditLinks staat uit voor de PDF: een bewerklink kost per bevinding
     * een zoektocht langs alle Filament-resources plus een find() op het model,
     * en in een PDF is er niets om op te klikken. Op een scan van driehonderd
     * pagina's waren dat ruim vierduizend queries voor niets.
     *
     * $withFixes staat om dezelfde reden uit voor de PDF: de PDF-opmaak
     * gebruikt de sleutels `fix` en `pending_fixes` niet, en de opzoekquery
     * kost anders alsnog een query per controle voor niets. De sleutels
     * blijven wel altijd in de array staan, met `null` en `0`, zodat de vorm
     * van het antwoord niet verandert met wie er belt.
     *
     * @return array<string, mixed>
     */
    public static function findings(SiteAudit $audit, string $checkKey, ?int $limit = 100, bool $withEditLinks = true, bool $withFixes = true): array
    {
        $check = app(SeoCheckRegistry::class)->check($checkKey);

        $query = $audit->findings()->where('check_key', $checkKey);

        $total = (clone $query)->count();

        // Alleen deze vier kolommen: before en after zijn json met de volledige
        // oude en nieuwe inhoud van een pagina, en dit scherm toont er niets
        // van. Ze meehalen is op een scan met honderden voorstellen megabytes
        // aan gegevens voor niets.
        $fixes = $withFixes
            ? SiteAuditFix::where('audit_id', $audit->id)
                ->where('check_key', $checkKey)
                ->select(['id', 'finding_id', 'page_id', 'status'])
                ->get()
            : collect();

        // Op bevinding en niet op pagina: een pagina met drie kapotte links
        // levert drie bevindingen en drie voorstellen met hetzelfde page_id.
        // Koppelen op pagina hield daar de laatste van over, en dan verstuurt
        // de knop bij regel A het voorstel van regel C terwijl A en B
        // "Doorgevoerd" melden voor werk dat daar nooit gedaan is.
        $perBevinding = $fixes
            ->filter(fn (SiteAuditFix $fix): bool => $fix->finding_id !== null)
            ->keyBy(fn (SiteAuditFix $fix): int => (int) $fix->finding_id);

        // Een sitebrede fixer legt geen bevinding vast, want zijn ene voorstel
        // dekt ze allemaal. Rijen uit een oudere scan, van voordat finding_id
        // bestond, vallen hier ook op hun oude gedrag terug.
        $zonderBevinding = $fixes
            ->filter(fn (SiteAuditFix $fix): bool => $fix->finding_id === null)
            ->keyBy(fn (SiteAuditFix $fix): string => (string) ($fix->page_id ?? 'site'));

        $rows = $query
            ->with('page')
            ->orderByDesc('is_new')
            ->orderBy('id')
            ->when($limit !== null, fn ($query) => $query->limit($limit))
            ->get()
            ->map(fn ($finding): array => [
                'url' => $finding->url ?: $finding->page?->url,
                'is_new' => (bool) $finding->is_new,
                'context' => $finding->context ?? [],
                'edit_url' => ($withEditLinks && $finding->page) ? PageEditLink::for($finding->page) : null,
                'fix' => ($fix = $perBevinding[(int) $finding->id]
                    ?? $zonderBevinding[(string) ($finding->page_id ?? 'site')]
                    ?? null)
                    ? ['id' => (int) $fix->id, 'status' => $fix->status]
                    : null,
            ])
            ->all();

        return [
            'label' => $check?->label() ?? $checkKey,
            'description' => $check?->description() ?? '',
            'how_to_fix' => $check?->howToFix() ?? '',
            'rows' => $rows,
            // Zonder dit getal leest een afgekapte lijst als de volledige
            // lijst, en dan lijkt het probleem kleiner dan het is.
            'remaining' => max(0, $total - count($rows)),
            'check_key' => $checkKey,
            'pending_fixes' => $fixes->where('status', SiteAuditFix::STATUS_PENDING)->count(),
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

        $maxPages = (int) ($settings['max_pages'] ?? PHP_INT_MAX);

        // Nul is geen limiet, dus dan valt er ook niets te waarschuwen.
        if ($maxPages > 0 && (int) $audit->pages_crawled >= $maxPages) {
            $warnings[] = __('De paginalimiet van :limiet is bereikt, dus de site is mogelijk niet helemaal gescand.', [
                'limiet' => $settings['max_pages'],
            ]);
        }

        return $warnings;
    }
}
