<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Http;
use Dashed\DashedSeo\Models\SiteAudit;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Dashed\DashedSeo\Crawler\PageParser;
use Dashed\DashedSeo\Crawler\ParsedPage;
use Dashed\DashedSeo\Models\SiteAuditLink;
use Dashed\DashedSeo\Models\SiteAuditPage;
use Dashed\DashedSeo\Models\SiteAuditAsset;
use Dashed\DashedSeo\Crawler\RobotsTxt;
use Dashed\DashedSeo\Crawler\ModelResolver;
use Dashed\DashedSeo\Crawler\UrlNormalizer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class CrawlSiteAuditBatchJob implements ShouldQueue
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
        if (! $audit || $audit->status !== SiteAudit::STATUS_CRAWLING) {
            return;
        }

        $this->reclaimStrandedPages($audit);

        $batch = $audit->pages()->pending()->orderBy('id')->limit(config('dashed-seo.crawler.batch_size'))->get();

        if ($batch->isEmpty()) {
            $audit->update([
                'status' => SiteAudit::STATUS_ANALYSING,
                'heartbeat_at' => now(),
                'pages_crawled' => $audit->pages()->where('crawl_status', SiteAuditPage::CRAWL_DONE)->count(),
            ]);

            AnalyseSiteAuditJob::dispatch($audit->id);

            return;
        }

        foreach ($batch as $page) {
            $this->crawl($audit, $page);
            usleep(config('dashed-seo.crawler.delay_ms') * 1000);
        }

        $audit->update([
            'heartbeat_at' => now(),
            'urls_found' => $audit->pages()->count(),
        ]);

        self::dispatch($audit->id);
    }

    protected function reclaimStrandedPages(SiteAudit $audit): void
    {
        // Een rij die op crawling staat maar al langer dan een ruime
        // veelvoud van de fetch-timeout niet is bijgewerkt, hoort bij een
        // worker die halverwege een verzoek is gestopt (killed job, deploy,
        // OOM). Zonder reclaim blijft die rij voor altijd crawling: geen
        // latere batch pakt hem op via de pending()-scope, de voorraad lijkt
        // "leeg" terwijl die pagina nooit echt bezocht is, en de scan gaat
        // door naar analyse met een pagina die stilzwijgend ontbreekt. Vier
        // keer de timeout, met een bodem van een minuut, laat een oprecht
        // trage pagina met een korte timeout-instelling met rust terwijl hij
        // nog in behandeling is.
        $staleAfterSeconds = max(60, (int) config('dashed-seo.crawler.timeout') * 4);

        $audit->pages()
            ->where('crawl_status', SiteAuditPage::CRAWL_CRAWLING)
            ->where('updated_at', '<', now()->subSeconds($staleAfterSeconds))
            ->update(['crawl_status' => SiteAuditPage::CRAWL_PENDING]);
    }

    protected function crawl(SiteAudit $audit, SiteAuditPage $page): void
    {
        $page->update(['crawl_status' => SiteAuditPage::CRAWL_CRAWLING]);

        $started = microtime(true);

        try {
            $response = Http::timeout(config('dashed-seo.crawler.timeout'))
                ->withHeaders([
                    'User-Agent' => config('dashed-seo.crawler.user_agent'),
                    config('dashed-seo.crawler.bypass_cache_header') => '1',
                ])
                ->withoutRedirecting()
                ->get($page->url);
        } catch (\Throwable $exception) {
            $page->update([
                'crawl_status' => SiteAuditPage::CRAWL_ERROR,
                'fetch_error' => $exception->getMessage(),
                'crawled_at' => now(),
            ]);

            return;
        }

        $elapsed = (int) round((microtime(true) - $started) * 1000);
        $status = $response->status();
        $body = $response->body();
        $contentType = strtolower($response->header('Content-Type') ?? '');
        // Een ontbrekende Content-Type-header betekent onbekend, niet
        // "geen HTML": de meeste servers sturen hem altijd, maar als hij
        // ontbreekt is aannemen dat het geen HTML is precies verkeerd om.
        // Alleen een header die expliciet iets anders zegt (bv. image/png)
        // blokkeert het parsen.
        $isHtml = $contentType === '' || str_contains($contentType, 'html');
        $parsed = null;

        // Guzzle decodeert een gzip/br-antwoord automatisch (default
        // decode_content) en hernoemt daarbij Content-Encoding naar
        // x-encoded-content-encoding, waarna het de originele header
        // verwijdert (vendor/guzzlehttp/guzzle/src/Handler/EasyHandle.php:102,
        // vendor/guzzlehttp/guzzle/src/Handler/StreamHandler.php:316). Zonder
        // de hernoemde header te lezen staat content_encoding hier op elk
        // echt gecomprimeerd antwoord structureel leeg, en vuurt
        // no_compression voor elke pagina van elke gecomprimeerde site. Zelfde
        // aanpak als storeAssets() hieronder.
        $contentEncoding = $response->header('Content-Encoding');
        if ($contentEncoding === '') {
            $contentEncoding = $response->header('x-encoded-content-encoding');
        }

        $attributes = [
            'crawl_status' => SiteAuditPage::CRAWL_DONE,
            'status_code' => $status,
            'response_time_ms' => $elapsed,
            'content_type' => $response->header('Content-Type'),
            'content_encoding' => $contentEncoding === '' ? null : $contentEncoding,
            'html_size' => strlen($body),
            'crawled_at' => now(),
            'indexable' => $status === 200,
        ];

        if ($status >= 300 && $status < 400) {
            $location = $response->header('Location');
            $attributes['redirect_target'] = $location === null
                ? null
                // De Location-header wordt opgelost tegen de URL die net is
                // opgevraagd, niet tegen de basis-URL van de site: een
                // relatieve Location op een pagina buiten de root (bv.
                // /blog/oud -> "nieuw") hoort relatief aan /blog/ op te
                // lossen, niet aan de root.
                : UrlNormalizer::normalize($location, $page->url);
            $attributes['indexable'] = false;
        }

        if ($status === 200 && $isHtml) {
            $parsed = PageParser::parse($body, $page->url);

            $attributes = array_merge($attributes, [
                'title' => $parsed->title,
                'meta_description' => $parsed->metaDescription,
                'canonical' => $parsed->canonical,
                'robots_meta' => $parsed->robotsMeta,
                'html_lang' => $parsed->htmlLang,
                'h1s' => $parsed->h1s,
                'heading_counts' => $parsed->headingCounts,
                'word_count' => $parsed->wordCount,
                'hreflangs' => $parsed->hreflangs,
                'images' => $parsed->images,
                'stylesheets' => $parsed->stylesheets,
                'scripts' => $parsed->scripts,
                'indexable' => ! str_contains(strtolower($parsed->robotsMeta ?? ''), 'noindex'),
            ]);

            $model = ModelResolver::resolve($page->url, $audit->site_id);
            if ($model !== null) {
                $attributes['model_type'] = $model['model_type'];
                $attributes['model_id'] = $model['model_id'];
            }
        }

        $page->update($attributes);

        // Pas hier, want storeLinks heeft het id van de zojuist bijgewerkte rij
        // nodig en storeAssets doet eigen verzoeken.
        if ($parsed !== null) {
            $this->storeLinks($audit, $page, $parsed->links);
            $this->storeAssets($audit, $parsed);
        }
    }

    protected function storeLinks(SiteAudit $audit, SiteAuditPage $page, array $links): void
    {
        $maxPages = $audit->settings['max_pages'] ?? config('dashed-seo.crawler.max_pages');
        $maxDepth = $audit->settings['max_depth'] ?? config('dashed-seo.crawler.max_depth');
        $robots = $this->robotsFromSettings($audit);
        $rows = [];

        foreach ($links as $link) {
            $isExternal = ! UrlNormalizer::isSameHost($link['url'], $audit->base_url);

            $rows[] = [
                'audit_id' => $audit->id,
                'from_page_id' => $page->id,
                'to_url' => $link['url'],
                'to_url_hash' => UrlNormalizer::hash($link['url']),
                'to_page_id' => null,
                'anchor' => $link['anchor'],
                'rel' => $link['rel'],
                'is_external' => $isExternal,
                'is_image_link' => $link['is_image_link'],
            ];

            if ($isExternal || $page->depth >= $maxDepth) {
                continue;
            }

            if ($audit->pages()->count() >= $maxPages) {
                continue;
            }

            $path = parse_url($link['url'], PHP_URL_PATH) ?: '/';
            // Zelfde behandeling als StartSiteAuditJob geeft aan sitemap-
            // url's: een pad dat robots.txt verbiedt wordt vastgelegd als
            // geblokkeerd in plaats van opgehaald. Zonder dit toetste alleen
            // de sitemap-instroom aan robots.txt, en kroop de crawler gewoon
            // door naar paden als /winkelwagen of /uitloggen die via gewone
            // links ontdekt worden.
            $blocked = ! $robots->allows($path);

            SiteAuditPage::firstOrCreate(
                ['audit_id' => $audit->id, 'url_hash' => UrlNormalizer::hash($link['url'])],
                [
                    'url' => $link['url'],
                    'path' => $path,
                    'crawl_status' => $blocked ? SiteAuditPage::CRAWL_DONE : SiteAuditPage::CRAWL_PENDING,
                    'depth' => $page->depth + 1,
                    'discovered_from_page_id' => $page->id,
                    'blocked_by_robots' => $blocked,
                ]
            );
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            SiteAuditLink::insert($chunk);
        }
    }

    protected function robotsFromSettings(SiteAudit $audit): RobotsTxt
    {
        $settings = $audit->settings ?? [];

        return new RobotsTxt(
            disallows: $settings['robots_disallows'] ?? [],
            allows: $settings['robots_allows'] ?? [],
            unavailable: $settings['robots_unavailable'] ?? false,
        );
    }

    protected function storeAssets(SiteAudit $audit, ParsedPage $parsed): void
    {
        $targets = [];
        foreach ($parsed->stylesheets as $url) {
            $targets[$url] = SiteAuditAsset::TYPE_CSS;
        }
        foreach ($parsed->scripts as $url) {
            $targets[$url] = SiteAuditAsset::TYPE_JS;
        }
        foreach ($parsed->images as $image) {
            $targets[$image['src']] = SiteAuditAsset::TYPE_IMAGE;
        }

        foreach ($targets as $url => $type) {
            $hash = UrlNormalizer::hash($url);

            if (SiteAuditAsset::where('audit_id', $audit->id)->where('url_hash', $hash)->exists()) {
                continue;
            }

            try {
                $response = Http::timeout(config('dashed-seo.crawler.timeout'))
                    ->withHeaders(['User-Agent' => config('dashed-seo.crawler.user_agent')])
                    ->get($url);
            } catch (\Throwable) {
                SiteAuditAsset::create([
                    'audit_id' => $audit->id,
                    'url' => $url,
                    'url_hash' => $hash,
                    'type' => $type,
                    'status_code' => null,
                ]);

                continue;
            }

            SiteAuditAsset::create([
                'audit_id' => $audit->id,
                'url' => $url,
                'url_hash' => $hash,
                'type' => $type,
                'status_code' => $response->status(),
                // Niet de Content-Length-header: Guzzle pakt een gzip/br-
                // gecodeerd antwoord automatisch uit voordat ->body() iets
                // teruggeeft, dus de header meldt de gecomprimeerde grootte
                // op de kabel terwijl de checks (css_too_large/js_too_large)
                // de echte bestandsgrootte willen. De body staat al in het
                // geheugen op deze regel, dus strlen() kost niets extra.
                'size' => strlen($response->body()),
                // Guzzle decodeert een gzip/br-antwoord automatisch (default
                // decode_content) en hernoemt daarbij Content-Encoding naar
                // x-encoded-content-encoding, waarna het de originele header
                // verwijdert (vendor/guzzlehttp/guzzle/src/Handler/EasyHandle.php:102,
                // vendor/guzzlehttp/guzzle/src/Handler/StreamHandler.php:316).
                // Content-Encoding is dus bij een echt gecomprimeerd antwoord
                // altijd al weg tegen de tijd dat wij hier kijken; zonder de
                // hernoemde header te lezen zou compressed hier structureel
                // false zijn voor precies de antwoorden die wel gecomprimeerd
                // waren.
                //
                // ->header() proxyt PSR-7's getHeaderLine(), die voor een
                // ontbrekende header '' teruggeeft, nooit null: een check op
                // !== null staat dus altijd op true en compressed zou voor
                // elk antwoord waar zijn, gecomprimeerd of niet. Vandaar de
                // vergelijking met '' in plaats van null.
                'compressed' => $response->header('Content-Encoding') !== ''
                    || $response->header('x-encoded-content-encoding') !== '',
            ]);
        }
    }
}
