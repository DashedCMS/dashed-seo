<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Http;
use Dashed\DashedSeo\Models\SiteAudit;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedSeo\Crawler\RobotsTxt;
use Illuminate\Queue\InteractsWithQueue;
use Dashed\DashedSeo\Models\SiteAuditPage;
use Dashed\DashedSeo\Crawler\ModelResolver;
use Dashed\DashedSeo\Crawler\SitemapReader;
use Dashed\DashedSeo\Crawler\UrlNormalizer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedSeo\Settings\AuditSettings;
use Dashed\DashedSeo\Exceptions\BaseUrlUnresolvableException;

class StartSiteAuditJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $auditId)
    {
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

        // De instellingen van deze site bepalen vanaf hier alles: de limieten
        // die de crawler leest, en de drempels waarmee de controles oordelen.
        $settings = AuditSettings::forSite($audit->site_id);
        $settings->apply();

        // De basis-URL op de scan wint van de instelling: het commando en de
        // knop zetten hem daar al op de gewenste waarde, en een scan die is
        // gestart voor een adres moet dat adres houden ook als iemand de
        // instelling ondertussen wijzigt.
        $configuredBase = rtrim($audit->base_url ?: $settings->baseUrl, '/');

        try {
            $base = $this->resolveEffectiveBaseUrl($configuredBase);
        } catch (BaseUrlUnresolvableException) {
            $audit->update([
                'status' => SiteAudit::STATUS_FAILED,
                'failure_reason' => __('De basis-URL :url kon niet worden opgelost, de scan is niet gestart.', ['url' => $configuredBase]),
                'finished_at' => now(),
            ]);

            return;
        }

        if ($base === null) {
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

        // Het ruwe aantal adressen dat de sitemap opleverde, voor normalisatie
        // en ontdubbeling. Zonder dit is een sitemap waarvan elke regel afvalt
        // (bijvoorbeeld omdat ze naar een staging-host wijzen) in de
        // opgeslagen gegevens niet te onderscheiden van een site zonder
        // sitemap, en ziet de scan er geslaagd uit terwijl hij bijna niets
        // gezien heeft.
        $sitemapRowsSeen = count($sitemapUrls);

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
            'base_url' => $base,
            'started_at' => now(),
            'heartbeat_at' => now(),
            'urls_found' => count($rows),
            'settings' => array_merge($audit->settings ?? [], $settings->toSnapshot(), [
                'robots_has_sitemap' => $robots->sitemaps() !== [],
                'sitemap_url_count' => $sitemapUrlCount,
                'sitemap_rows_seen' => $sitemapRowsSeen,
                // CrawlSiteAuditBatchJob draait in een eigen (queue-)proces en
                // kan dus geen RobotsTxt-instantie van dit object erven: het
                // moet de regels herbouwen uit settings om ontdekte links
                // (niet alleen sitemap-url's) tegen robots.txt te toetsen.
                'robots_disallows' => $robots->disallows(),
                'robots_allows' => $robots->allowRules(),
                'robots_unavailable' => $robots->isUnavailable(),
                // De URL zoals de gebruiker/instellingen 'm hebben opgegeven,
                // voordat de bereikbaarheidscheck hierboven eventueel een
                // redirect volgde. base_url zelf wordt hierboven al op de
                // opgeloste versie gezet (nodig omdat CrawlSiteAuditBatchJob
                // in een eigen proces draait en $audit->base_url vers uit de
                // database leest), dus zonder dit apart vast te leggen is
                // nergens meer te zien dat er uberhaupt een redirect gevolgd is.
                'base_url_configured' => $configuredBase,
            ]),
        ]);

        CrawlSiteAuditBatchJob::dispatch($audit->id);
    }

    // De bereikbaarheidscheck volgt redirects (Http::get()'s standaardgedrag),
    // terwijl CrawlSiteAuditBatchJob dat bewust niet doet: die legt een
    // redirect vast als bevinding in plaats van 'm te volgen. Een basis-URL
    // die doorstuurt naar zijn canonieke vorm (http naar https, apex naar
    // www) is de vorm van bijna elke echte site, dus zonder dit hier op te
    // lossen ziet deze check een gezonde 200 op de uiteindelijke URL, terwijl
    // de crawl daarna meteen strandt op de eerste hop en nul pagina's
    // bezoekt. Deze methode geeft daarom niet alleen bereikbaar/onbereikbaar
    // terug, maar de URL waar het verzoek daadwerkelijk op uitkwam, zodat de
    // rest van de scan (robots.txt, sitemap, geheugen van bezochte pagina's)
    // op diezelfde URL verder werkt.
    protected function resolveEffectiveBaseUrl(string $base): ?string
    {
        try {
            $response = Http::timeout(config('dashed-seo.crawler.timeout'))
                ->withHeaders(['User-Agent' => config('dashed-seo.crawler.user_agent')])
                ->get($base);
        } catch (\Throwable) {
            return null;
        }

        if ($response->status() >= 500) {
            return null;
        }

        // effectiveUri() komt van Guzzle's TransferStats, die Laravel's
        // PendingRequest via de on_stats-optie altijd meestuurt (niet alleen
        // als allow_redirects.track_redirects aanstaat), dus dit is de URI
        // van het verzoek dat het antwoord echt opleverde na het volgen van
        // eventuele redirects. Zonder redirect is dat gewoon $base zelf.
        //
        // Als effectiveUri() hier toch null teruggeeft, of als de opgeloste
        // URL door UrlNormalizer afgewezen wordt, is er geen betrouwbare URL
        // om de rest van de scan op te baseren. Stilzwijgend $base
        // teruggeven zou hier precies het symptoom reproduceren dat deze
        // methode moet voorkomen: een scan die doorzet op het verkeerde
        // adres en er toch geslaagd uitziet. Daarom gooien we in plaats
        // daarvan; handle() vangt dit apart op om de audit hardop te laten
        // mislukken, met een andere reden dan de onbereikbaar-melding
        // hieronder.
        $effectiveUri = $response->effectiveUri();
        if ($effectiveUri === null) {
            throw new BaseUrlUnresolvableException($base);
        }

        // Genormaliseerd zodat een meegekomen trailing slash de rootpagina
        // niet onder een andere hash opslaat dan waar de crawl 'm later op
        // zoekt (zie UrlNormalizer::normalize en de root-terugval hieronder).
        $normalized = UrlNormalizer::normalize((string) $effectiveUri, $base);
        if ($normalized === null) {
            throw new BaseUrlUnresolvableException($base);
        }

        return rtrim($normalized, '/');
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
