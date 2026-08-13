<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\Support;

use Illuminate\Support\Facades\Http;
use Dashed\DashedSeo\Models\SiteAudit;
use Dashed\DashedSeo\Models\SiteAuditLink;
use Dashed\DashedSeo\Models\SiteAuditAsset;

class ExternalLinkChecker
{
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

        foreach ($urls as $url => $hash) {
            if (SiteAuditAsset::where('audit_id', $audit->id)->where('url_hash', $hash)->exists()) {
                continue;
            }

            try {
                $response = Http::timeout(config('dashed-seo.crawler.timeout'))
                    ->withHeaders(['User-Agent' => config('dashed-seo.crawler.user_agent')])
                    ->head($url);
                $status = $response->status();
            } catch (\Throwable) {
                $status = null;
            }

            SiteAuditAsset::create([
                'audit_id' => $audit->id,
                'url' => $url,
                'url_hash' => $hash,
                'type' => SiteAuditAsset::TYPE_EXTERNAL,
                'status_code' => $status,
            ]);
        }
    }
}
