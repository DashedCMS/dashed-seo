<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Crawler;

use Illuminate\Support\Facades\Http;

class SitemapReader
{
    public static function urls(string $baseUrl, array $sitemapUrls = []): array
    {
        if ($sitemapUrls === []) {
            $sitemapUrls = [rtrim($baseUrl, '/') . '/sitemap.xml'];
        }

        $urls = [];
        foreach ($sitemapUrls as $sitemapUrl) {
            foreach (self::read($sitemapUrl) as $url) {
                $urls[] = $url;
            }
        }

        return array_values(array_unique($urls));
    }

    protected static function read(string $sitemapUrl, int $depth = 0): array
    {
        if ($depth > 2) {
            return [];
        }

        try {
            $response = Http::timeout(config('dashed-seo.crawler.timeout'))
                ->withHeaders(['User-Agent' => config('dashed-seo.crawler.user_agent')])
                ->get($sitemapUrl);
        } catch (\Throwable) {
            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response->body());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            return [];
        }

        $urls = [];

        if ($xml->getName() === 'sitemapindex') {
            foreach ($xml->sitemap as $entry) {
                $urls = array_merge($urls, self::read((string) $entry->loc, $depth + 1));
            }

            return $urls;
        }

        foreach ($xml->url as $entry) {
            $location = trim((string) $entry->loc);
            if ($location !== '') {
                $urls[] = $location;
            }
        }

        return $urls;
    }
}
