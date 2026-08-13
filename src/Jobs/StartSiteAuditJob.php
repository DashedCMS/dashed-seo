<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Http;
use Dashed\DashedSeo\Models\SiteAudit;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Dashed\DashedSeo\Crawler\RobotsTxt;
use Dashed\DashedSeo\Models\SiteAuditPage;
use Dashed\DashedSeo\Crawler\ModelResolver;
use Dashed\DashedSeo\Crawler\SitemapReader;
use Dashed\DashedSeo\Crawler\UrlNormalizer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class StartSiteAuditJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $auditId)
    {
        $this->onQueue('seo');
    }

    public function handle(): void
    {
        $audit = SiteAudit::find($this->auditId);
        if (! $audit || $audit->status !== SiteAudit::STATUS_PENDING) {
            return;
        }

        // Een tweede scan van dezelfde site in dit proces mag nooit leunen op
        // koppelingen die de vorige scan al warm draaide: die kunnen sinds
        // dien verouderd zijn (nieuwe pagina, gewijzigde slug). Dit moet dus
        // bij elke scan gebeuren, niet alleen wanneer de site wisselt, en
        // voordat er iets anders met de resolver gebeurt.
        ModelResolver::flush();

        $base = rtrim($audit->base_url, '/');

        if (! $this->baseUrlAnswers($base)) {
            $audit->update([
                'status' => SiteAudit::STATUS_FAILED,
                'failure_reason' => __('De basis-URL is niet bereikbaar, de scan is niet gestart.'),
                'finished_at' => now(),
            ]);

            return;
        }

        $robots = RobotsTxt::fetch($base);

        // Onbeschikbaar (5xx, timeout, uitzondering) betekent dat allows()
        // voor elk pad false teruggeeft: de veilige lezing, maar dat levert
        // stilzwijgend een scan van nul pagina's op die als geslaagd oogt.
        // Faal daarom expliciet in plaats van door te gaan met een lege
        // werkvoorraad.
        if ($robots->isUnavailable()) {
            $audit->update([
                'status' => SiteAudit::STATUS_FAILED,
                'failure_reason' => __('robots.txt kon niet gelezen worden, de scan is niet gestart.'),
                'finished_at' => now(),
            ]);

            return;
        }

        $sitemapUrls = SitemapReader::urls($base, $robots->sitemaps());

        $rows = [];
        foreach ($sitemapUrls as $url) {
            $normalized = UrlNormalizer::normalize($url, $base);
            if ($normalized === null || ! UrlNormalizer::isSameHost($normalized, $base)) {
                continue;
            }

            $rows[UrlNormalizer::hash($normalized)] = $this->row($audit, $normalized, true, $robots);
        }

        // Hoeveel bruikbare adressen de sitemap zelf opleverde, voordat de
        // terugval hieronder $rows eventueel vult met de enkele root-rij.
        // MissingFromSitemapCheck leunt hierop om te weten of er uberhaupt
        // een sitemap is om iets in te missen.
        $sitemapUrlCount = count($rows);

        if ($rows === []) {
            $root = UrlNormalizer::normalize($base . '/', $base);
            $rows[UrlNormalizer::hash($root)] = $this->row($audit, $root, false, $robots);
        }

        SiteAuditPage::insert(array_values($rows));

        $audit->update([
            'status' => SiteAudit::STATUS_CRAWLING,
            'started_at' => now(),
            'heartbeat_at' => now(),
            'urls_found' => count($rows),
            'settings' => array_merge($audit->settings ?? [], [
                'robots_has_sitemap' => $robots->sitemaps() !== [],
                'sitemap_url_count' => $sitemapUrlCount,
                'max_pages' => config('dashed-seo.crawler.max_pages'),
                'max_depth' => config('dashed-seo.crawler.max_depth'),
                // CrawlSiteAuditBatchJob draait in een eigen (queue-)proces en
                // kan dus geen RobotsTxt-instantie van dit object erven: het
                // moet de regels herbouwen uit settings om ontdekte links
                // (niet alleen sitemap-url's) tegen robots.txt te toetsen.
                'robots_disallows' => $robots->disallows(),
                'robots_allows' => $robots->allowRules(),
                'robots_unavailable' => $robots->isUnavailable(),
            ]),
        ]);

        CrawlSiteAuditBatchJob::dispatch($audit->id);
    }

    protected function baseUrlAnswers(string $base): bool
    {
        try {
            $response = Http::timeout(config('dashed-seo.crawler.timeout'))
                ->withHeaders(['User-Agent' => config('dashed-seo.crawler.user_agent')])
                ->get($base);
        } catch (\Throwable) {
            return false;
        }

        return $response->status() < 500;
    }

    protected function row(SiteAudit $audit, string $url, bool $inSitemap, RobotsTxt $robots): array
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '/';

        return [
            'audit_id' => $audit->id,
            'url' => $url,
            'url_hash' => UrlNormalizer::hash($url),
            'path' => $path,
            'crawl_status' => $robots->allows($path) ? SiteAuditPage::CRAWL_PENDING : SiteAuditPage::CRAWL_DONE,
            'depth' => 0,
            'in_sitemap' => $inSitemap,
            'blocked_by_robots' => ! $robots->allows($path),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
