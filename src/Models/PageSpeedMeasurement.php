<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Models;

use Illuminate\Database\Eloquent\Model;

class PageSpeedMeasurement extends Model
{
    public const STRATEGY_MOBILE = 'mobile';
    public const STRATEGY_DESKTOP = 'desktop';

    public const STRATEGIES = [self::STRATEGY_MOBILE, self::STRATEGY_DESKTOP];

    public $timestamps = false;

    protected $table = 'dashed__pagespeed_measurements';

    protected $guarded = [];

    protected $casts = [
        'measured_at' => 'datetime',
        'performance_score' => 'integer',
        'lab_lcp_ms' => 'integer',
        'lab_fcp_ms' => 'integer',
        'lab_tbt_ms' => 'integer',
        'lab_cls' => 'float',
        'field_lcp_ms' => 'integer',
        'field_inp_ms' => 'integer',
        'field_cls' => 'float',
    ];

    /**
     * Grenzen voor "slecht" volgens web.dev (Core Web Vitals). Gelden voor
     * veld- en labwaarden; INP bestaat alleen als veldwaarde.
     */
    public const POOR_LCP_MS = 4000;
    public const POOR_INP_MS = 500;
    public const POOR_CLS = 0.25;

    /** @return array<int, string> welke metingen in het slechte gebied liggen */
    public function poorMetrics(): array
    {
        $lcp = $this->field_lcp_ms ?? $this->lab_lcp_ms;
        $cls = $this->field_cls ?? $this->lab_cls;

        return array_values(array_filter([
            $lcp !== null && $lcp > self::POOR_LCP_MS ? 'lcp' : null,
            $this->field_inp_ms !== null && $this->field_inp_ms > self::POOR_INP_MS ? 'inp' : null,
            $cls !== null && $cls > self::POOR_CLS ? 'cls' : null,
        ]));
    }
}
