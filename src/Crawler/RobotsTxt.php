<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Crawler;

use Illuminate\Support\Facades\Http;

class RobotsTxt
{
    public function __construct(
        protected array $disallows = [],
        protected array $sitemaps = [],
        protected array $allows = [],
        protected bool $found = false,
        protected bool $unavailable = false,
    ) {
    }

    public static function fetch(string $baseUrl): self
    {
        try {
            $response = Http::timeout(config('dashed-seo.crawler.timeout'))
                ->withHeaders(['User-Agent' => config('dashed-seo.crawler.user_agent')])
                ->get(rtrim($baseUrl, '/') . '/robots.txt');
        } catch (\Throwable) {
            // Network error or timeout - we cannot verify the rules, assume everything is disallowed
            return new self(unavailable: true, found: false);
        }

        // Server error (5xx) - we cannot verify the rules, assume everything is disallowed
        if ($response->serverError()) {
            return new self(unavailable: true, found: false);
        }

        // Client error (4xx like 404) - no rules exist, everything is allowed
        if (! $response->successful()) {
            return new self(found: false);
        }

        $disallows = [];
        $allows = [];
        $sitemaps = [];
        $currentGroupAgents = [];
        $currentGroupDisallows = [];
        $currentGroupAllows = [];
        $inUserAgentBlock = false;
        $hasSeenUserAgent = false;

        foreach (preg_split('/\R/', $response->body()) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            [$field, $value] = array_pad(explode(':', $line, 2), 2, '');
            $field = strtolower(trim($field));
            $value = trim($value);

            if ($field === 'user-agent') {
                // Transition from directives back to user-agent: finalize previous group
                if (!$inUserAgentBlock && !empty($currentGroupAgents)) {
                    if (in_array('*', $currentGroupAgents)) {
                        $disallows = array_merge($disallows, $currentGroupDisallows);
                        $allows = array_merge($allows, $currentGroupAllows);
                    }
                    $currentGroupAgents = [];
                    $currentGroupDisallows = [];
                    $currentGroupAllows = [];
                }

                if ($value !== '') {
                    $currentGroupAgents[] = $value;
                    $hasSeenUserAgent = true;
                }
                $inUserAgentBlock = true;

                continue;
            }

            // We're no longer in the user-agent block
            $inUserAgentBlock = false;

            if ($field === 'sitemap' && $value !== '') {
                $sitemaps[] = $value;

                continue;
            }

            // Only add rules if we've seen a User-agent line
            if ($hasSeenUserAgent) {
                if ($field === 'disallow' && $value !== '') {
                    $currentGroupDisallows[] = $value;
                }

                if ($field === 'allow' && $value !== '') {
                    $currentGroupAllows[] = $value;
                }
            }
        }

        // Finalize the last group
        if (!empty($currentGroupAgents) && in_array('*', $currentGroupAgents)) {
            $disallows = array_merge($disallows, $currentGroupDisallows);
            $allows = array_merge($allows, $currentGroupAllows);
        }

        return new self(
            disallows: $disallows,
            sitemaps: $sitemaps,
            allows: $allows,
            found: true
        );
    }

    public function disallows(): array
    {
        return $this->disallows;
    }

    public function allowRules(): array
    {
        return $this->allows;
    }

    public function sitemaps(): array
    {
        return $this->sitemaps;
    }

    public function wasFound(): bool
    {
        return $this->found;
    }

    public function isUnavailable(): bool
    {
        return $this->unavailable;
    }

    public function allows(string $path): bool
    {
        // If robots.txt was unavailable (5xx, timeout, error), disallow everything
        if ($this->unavailable) {
            return false;
        }

        // Find the longest matching Disallow rule
        $longestDisallow = '';
        foreach ($this->disallows as $rule) {
            if (str_starts_with($path, $rule) && strlen($rule) > strlen($longestDisallow)) {
                $longestDisallow = $rule;
            }
        }

        // Find the longest matching Allow rule
        $longestAllow = '';
        foreach ($this->allows as $rule) {
            if (str_starts_with($path, $rule) && strlen($rule) > strlen($longestAllow)) {
                $longestAllow = $rule;
            }
        }

        // If longest matching Allow is at least as long as longest matching Disallow, allow it
        if (strlen($longestAllow) >= strlen($longestDisallow)) {
            return true;
        }

        // If longest Disallow is longer, disallow it
        if (strlen($longestDisallow) > 0) {
            return false;
        }

        // No rules matched, allow everything
        return true;
    }
}
