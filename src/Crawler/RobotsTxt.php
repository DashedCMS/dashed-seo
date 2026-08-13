<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Crawler;

use Illuminate\Support\Facades\Http;

class RobotsTxt
{
    public function __construct(
        protected array $disallows = [],
        protected array $sitemaps = [],
        protected bool $found = false,
    ) {
    }

    public static function fetch(string $baseUrl): self
    {
        try {
            $response = Http::timeout(config('dashed-seo.crawler.timeout'))
                ->withHeaders(['User-Agent' => config('dashed-seo.crawler.user_agent')])
                ->get(rtrim($baseUrl, '/') . '/robots.txt');
        } catch (\Throwable) {
            return new self();
        }

        if (! $response->successful()) {
            return new self();
        }

        $disallows = [];
        $sitemaps = [];
        $appliesToUs = false;

        foreach (preg_split('/\R/', $response->body()) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            [$field, $value] = array_pad(explode(':', $line, 2), 2, '');
            $field = strtolower(trim($field));
            $value = trim($value);

            if ($field === 'sitemap' && $value !== '') {
                $sitemaps[] = $value;

                continue;
            }

            if ($field === 'user-agent') {
                $appliesToUs = $value === '*';

                continue;
            }

            if ($field === 'disallow' && $appliesToUs && $value !== '') {
                $disallows[] = $value;
            }
        }

        return new self($disallows, $sitemaps, true);
    }

    public function disallows(): array
    {
        return $this->disallows;
    }

    public function sitemaps(): array
    {
        return $this->sitemaps;
    }

    public function wasFound(): bool
    {
        return $this->found;
    }

    public function allows(string $path): bool
    {
        foreach ($this->disallows as $rule) {
            if (str_starts_with($path, $rule)) {
                return false;
            }
        }

        return true;
    }
}
