<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\Support;

use Illuminate\Support\Sleep;
use Illuminate\Support\Facades\Http;
use Dashed\DashedSeo\Models\SiteAudit;
use Dashed\DashedSeo\Models\SiteAuditLink;
use Dashed\DashedSeo\Models\SiteAuditAsset;

class ExternalLinkChecker
{
    /**
     * Statussen die niet betekenen dat de link kapot is: 429 is "te veel
     * verzoeken", wij zijn afgeremd en de pagina bestaat gewoon.
     * BrokenExternalLinkCheck slaat deze over.
     */
    public const NOT_BROKEN_STATUSES = [429];

    /**
     * Op deze statussen volgt een GET na de HEAD: veel servers (en CDN's)
     * weigeren of kennen HEAD niet en geven dan 403, 404, 405 of 501 terwijl
     * de pagina met een gewone GET gewoon bestaat.
     */
    public const RETRY_WITH_GET = [403, 404, 405, 501];

    public static function check(SiteAudit $audit): void
    {
        // select voor distinct, anders telt de id-kolom mee en is elke rij uniek.
        // orderBy is niet optioneel zodra er een limit op staat: zonder vaste
        // volgorde bepaalt de database-uitvoerder welke rijen overblijven,
        // wat kan afhangen van fysieke opslagvolgorde. Twee scans van
        // dezelfde, ongewijzigde site zouden dan een andere subset kunnen
        // controleren, en broken_external_link jittert zonder dat er iets
        // aan de site veranderd is.
        $urls = SiteAuditLink::where('audit_id', $audit->id)
            ->where('is_external', true)
            ->select(['to_url', 'to_url_hash'])
            ->distinct()
            ->orderBy('to_url')
            ->limit(config('dashed-seo.crawler.max_external_links'))
            ->pluck('to_url_hash', 'to_url');

        $perHost = [];
        $lastRequestAt = [];
        $maxPerHost = (int) config('dashed-seo.crawler.max_external_per_host', 25);
        $hostDelayMs = (int) config('dashed-seo.crawler.external_host_delay_ms', 1000);

        foreach ($urls as $url => $hash) {
            if (SiteAuditAsset::where('audit_id', $audit->id)->where('url_hash', $hash)->exists()) {
                continue;
            }

            // Hooguit zoveel links per externe host, en tussen twee verzoeken
            // naar dezelfde host een pauze: tweehonderd links naar dezelfde
            // social-media-site achter elkaar levert anders een rij 429's op,
            // en die stonden dan als kapotte link in het rapport.
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            $perHost[$host] = ($perHost[$host] ?? 0) + 1;

            if ($maxPerHost > 0 && $perHost[$host] > $maxPerHost) {
                continue;
            }

            if (isset($lastRequestAt[$host]) && $hostDelayMs > 0) {
                $wait = $hostDelayMs - (int) ((microtime(true) - $lastRequestAt[$host]) * 1000);

                if ($wait > 0) {
                    Sleep::usleep($wait * 1000);
                }
            }

            $status = self::status($url);
            $lastRequestAt[$host] = microtime(true);

            SiteAuditAsset::create([
                'audit_id' => $audit->id,
                'url' => $url,
                'url_hash' => $hash,
                'type' => SiteAuditAsset::TYPE_EXTERNAL,
                'status_code' => $status,
            ]);
        }
    }

    protected static function status(string $url): ?int
    {
        $request = fn () => Http::timeout(config('dashed-seo.crawler.timeout'))
            ->withHeaders(['User-Agent' => config('dashed-seo.crawler.user_agent')]);

        try {
            $status = $request()->head($url)->status();
        } catch (\Throwable) {
            $status = null;
        }

        if ($status === null || in_array($status, self::RETRY_WITH_GET, true)) {
            try {
                // stream: alleen de status is nodig, niet de hele pagina.
                $status = $request()->withOptions(['stream' => true])->get($url)->status();
            } catch (\Throwable) {
                // De status van de HEAD blijft staan.
            }
        }

        return $status;
    }
}
