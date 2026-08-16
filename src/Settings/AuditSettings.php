<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Settings;

use Dashed\DashedSeo\Models\SiteAudit;
use Dashed\DashedCore\Models\Customsetting;

/**
 * De instellingen van een site, op een plek. Drie partijen lezen hier:
 * de instellingenpagina toont ze, StartSiteAuditJob legt ze vast op de scan,
 * en de jobs zetten ze tijdens het draaien terug op de configuratie. Dat
 * laatste is wat een oud rapport uitlegbaar houdt: de controles lezen hun
 * drempels uit config(), dus zonder apply() zou een herberekening van een
 * scan uit maart de drempels van vandaag gebruiken.
 */
final class AuditSettings
{
    /** Elke drempel uit config('dashed-seo.thresholds') is per site in te stellen. */
    public const THRESHOLD_KEYS = [
        'title_max',
        'title_min',
        'description_max',
        'min_words',
        'slow_response_ms',
        'html_max_bytes',
        'css_max_bytes',
        'js_max_bytes',
        'max_redirect_hops',
    ];

    public function __construct(
        public readonly string $baseUrl,
        public readonly int $maxPages,
        public readonly int $maxDepth,
        public readonly int $delayMs,
        public readonly bool $weekly,
        public readonly int $staleAfterMinutes,
        /** @var array<string, int> */
        public readonly array $thresholds,
    ) {
    }

    public static function forSite(?string $siteId = null): self
    {
        $thresholds = [];
        foreach (self::THRESHOLD_KEYS as $key) {
            $thresholds[$key] = (int) self::setting(
                "site_audit_threshold_{$key}",
                $siteId,
                config("dashed-seo.thresholds.{$key}")
            );
        }

        return new self(
            baseUrl: rtrim((string) self::setting('site_audit_base_url', $siteId, config('app.url')), '/'),
            maxPages: (int) self::setting('site_audit_max_pages', $siteId, config('dashed-seo.crawler.max_pages')),
            maxDepth: (int) self::setting('site_audit_max_depth', $siteId, config('dashed-seo.crawler.max_depth')),
            delayMs: (int) self::setting('site_audit_delay_ms', $siteId, config('dashed-seo.crawler.delay_ms')),
            weekly: (bool) self::setting('site_audit_weekly', $siteId, false),
            staleAfterMinutes: (int) self::setting(
                'site_audit_stale_after_minutes',
                $siteId,
                config('dashed-seo.retention.stale_after_minutes')
            ),
            thresholds: $thresholds,
        );
    }

    public static function fromAudit(SiteAudit $audit): self
    {
        $stored = $audit->settings ?? [];

        // Alleen wat er echt in staat telt. Een scan van voor deze taak heeft
        // geen thresholds in settings, en die moet gewoon de huidige
        // configuratie blijven gebruiken in plaats van op nul te vallen.
        $thresholds = [];
        foreach (self::THRESHOLD_KEYS as $key) {
            if (isset($stored['thresholds'][$key])) {
                $thresholds[$key] = (int) $stored['thresholds'][$key];
            }
        }

        return new self(
            baseUrl: rtrim((string) ($stored['base_url_configured'] ?? $audit->base_url), '/'),
            maxPages: (int) ($stored['max_pages'] ?? config('dashed-seo.crawler.max_pages')),
            maxDepth: (int) ($stored['max_depth'] ?? config('dashed-seo.crawler.max_depth')),
            delayMs: (int) ($stored['delay_ms'] ?? config('dashed-seo.crawler.delay_ms')),
            weekly: (bool) ($stored['weekly'] ?? false),
            staleAfterMinutes: (int) ($stored['stale_after_minutes'] ?? config('dashed-seo.retention.stale_after_minutes')),
            thresholds: $thresholds,
        );
    }

    /** @return array<string, mixed> Wat er in de kolom settings terechtkomt. */
    public function toSnapshot(): array
    {
        return [
            'max_pages' => $this->maxPages,
            'max_depth' => $this->maxDepth,
            'delay_ms' => $this->delayMs,
            'weekly' => $this->weekly,
            'stale_after_minutes' => $this->staleAfterMinutes,
            'thresholds' => $this->thresholds,
        ];
    }

    public function apply(): void
    {
        config()->set('dashed-seo.crawler.max_pages', $this->maxPages);
        config()->set('dashed-seo.crawler.max_depth', $this->maxDepth);
        config()->set('dashed-seo.crawler.delay_ms', $this->delayMs);

        foreach ($this->thresholds as $key => $value) {
            config()->set("dashed-seo.thresholds.{$key}", $value);
        }
    }

    protected static function setting(string $name, ?string $siteId, mixed $default): mixed
    {
        $value = Customsetting::get($name, $siteId);

        // Een leeg tekstveld komt als lege string terug, en dat is geen
        // ingestelde waarde maar een niet-ingevuld veld.
        return ($value === null || $value === '') ? $default : $value;
    }
}
