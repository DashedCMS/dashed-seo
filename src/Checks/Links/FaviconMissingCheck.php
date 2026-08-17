<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\Links;

use Illuminate\Support\Facades\Http;
use Dashed\DashedSeo\Checks\BaseCheck;
use Dashed\DashedSeo\Models\SiteAudit;

class FaviconMissingCheck extends BaseCheck
{
    public function key(): string
    {
        return 'favicon_missing';
    }

    public function run(SiteAudit $audit): iterable
    {
        // De how-to-fix-tekst van deze controle vraagt om een link-element in
        // de head, dus een pagina die er een declareert telt zelf ook als
        // "heeft een favicon", los van of /favicon.ico toevallig antwoordt.
        if ($this->anyPageDeclaresAnIcon($audit)) {
            return;
        }

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

    protected function anyPageDeclaresAnIcon(SiteAudit $audit): bool
    {
        foreach ($this->pages($audit)->cursor() as $page) {
            if (($page->icons ?? []) !== []) {
                return true;
            }
        }

        return false;
    }
}
