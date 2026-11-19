<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\PageSpeed;

use Illuminate\Support\Facades\Http;

/**
 * Eén meting via de PageSpeed Insights API: Lighthouse-labdata en, als
 * Google genoeg verkeer op de URL ziet, CrUX-velddata.
 */
class PageSpeedClient
{
    public const ENDPOINT = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';

    public function __construct(protected string $apiKey)
    {
    }

    /**
     * @return array<string, mixed> kolommen voor PageSpeedMeasurement, met
     *                              'error' gevuld als de meting mislukte
     */
    public function measure(string $url, string $strategy): array
    {
        $response = rescue(fn () => Http::timeout(120)->get(self::ENDPOINT, [
            'url' => $url,
            'strategy' => $strategy,
            'category' => 'performance',
            'key' => $this->apiKey,
        ]), null, report: false);

        if (! $response) {
            return ['error' => 'PageSpeed Insights gaf geen antwoord.'];
        }

        if ($response->failed()) {
            return ['error' => mb_substr((string) ($response->json('error.message') ?? ('HTTP ' . $response->status())), 0, 1000)];
        }

        $lighthouse = $response->json('lighthouseResult', []);
        $audits = $lighthouse['audits'] ?? [];
        $score = $lighthouse['categories']['performance']['score'] ?? null;
        $field = $response->json('loadingExperience', []);
        $metrics = $field['metrics'] ?? [];

        $numeric = fn (string $audit) => isset($audits[$audit]['numericValue']) ? (float) $audits[$audit]['numericValue'] : null;
        $percentile = fn (string $metric) => isset($metrics[$metric]['percentile']) ? (float) $metrics[$metric]['percentile'] : null;

        $fieldCls = $percentile('CUMULATIVE_LAYOUT_SHIFT_SCORE');

        return [
            'performance_score' => $score !== null ? (int) round((float) $score * 100) : null,
            'lab_lcp_ms' => self::int($numeric('largest-contentful-paint')),
            'lab_fcp_ms' => self::int($numeric('first-contentful-paint')),
            'lab_tbt_ms' => self::int($numeric('total-blocking-time')),
            'lab_cls' => ($cls = $numeric('cumulative-layout-shift')) !== null ? round($cls, 3) : null,
            'field_lcp_ms' => self::int($percentile('LARGEST_CONTENTFUL_PAINT_MS')),
            'field_inp_ms' => self::int($percentile('INTERACTION_TO_NEXT_PAINT')),
            // CrUX geeft CLS maal honderd terug.
            'field_cls' => $fieldCls !== null ? round($fieldCls / 100, 3) : null,
            'field_category' => isset($field['overall_category']) ? (string) $field['overall_category'] : null,
            'error' => null,
        ];
    }

    protected static function int(?float $value): ?int
    {
        return $value !== null ? (int) round($value) : null;
    }
}
