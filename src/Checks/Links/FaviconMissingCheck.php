<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\Links;

use Illuminate\Support\Facades\Http;
use Dashed\DashedSeo\Models\SiteAudit;
use Dashed\DashedSeo\Checks\BaseCheck;

class FaviconMissingCheck extends BaseCheck
{
    public function key(): string
    {
        return 'favicon_missing';
    }

    public function run(SiteAudit $audit): iterable
    {
        $url = rtrim($audit->base_url, '/') . '/favicon.ico';

        try {
            $response = Http::timeout(config('dashed-seo.crawler.timeout'))
                ->withHeaders(['User-Agent' => config('dashed-seo.crawler.user_agent')])
                ->get($url);
            $found = $response->successful();
        } catch (\Throwable) {
            $found = false;
        }

        if (! $found) {
            yield $this->siteFinding(['expected' => $url]);
        }
    }
}
