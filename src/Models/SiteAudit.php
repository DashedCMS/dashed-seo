<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SiteAudit extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_CRAWLING = 'crawling';
    public const STATUS_ANALYSING = 'analysing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * De statussen waarin een scan bezig is. Dit is de enige lijst; de guard,
     * de knop, het commando en de jobs lezen hem hier. Uit de nieuwsbriefmodule
     * is de les meegekomen dat drie plekken die elk hun eigen lijst overtypen
     * vroeg of laat uiteenlopen, en dan start er iets wat volgens het scherm
     * niet kan starten.
     *
     * @return array<int, string>
     */
    public static function runningStatuses(): array
    {
        return [self::STATUS_PENDING, self::STATUS_CRAWLING, self::STATUS_ANALYSING];
    }

    protected $table = 'dashed__site_audits';

    protected $guarded = [];

    protected $casts = [
        'settings' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'heartbeat_at' => 'datetime',
    ];

    public function pages(): HasMany
    {
        return $this->hasMany(SiteAuditPage::class, 'audit_id');
    }

    public function links(): HasMany
    {
        return $this->hasMany(SiteAuditLink::class, 'audit_id');
    }

    public function findings(): HasMany
    {
        return $this->hasMany(SiteAuditFinding::class, 'audit_id');
    }

    public function checkResults(): HasMany
    {
        return $this->hasMany(SiteAuditCheckResult::class, 'audit_id');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(SiteAuditAsset::class, 'audit_id');
    }

    /**
     * De vorige afgeronde scan van dezelfde site. Het verschil met deze scan
     * is wat het rapport bovenaan zet: de score zelf beweegt weinig, de
     * beweging is wat er iets zegt.
     */
    public function previous(): ?self
    {
        return self::query()
            ->where('site_id', $this->site_id)
            ->where('status', self::STATUS_COMPLETED)
            ->where('id', '<', $this->id)
            ->orderByDesc('finished_at')
            ->orderByDesc('id')
            ->first();
    }

    public function isRunning(): bool
    {
        return in_array($this->status, self::runningStatuses(), true);
    }

    /**
     * Breekt een lopende scan af. Geeft false terug als er niets af te breken
     * viel, zodat de knop niet hoeft te gokken of het gelukt is. De jobs
     * stoppen vanzelf: ze controleren de status voordat ze verder gaan.
     */
    public function cancel(?string $reason = null): bool
    {
        if (! $this->isRunning()) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_CANCELLED,
            'failure_reason' => $reason ?: __('De scan is handmatig afgebroken.'),
            'finished_at' => now(),
        ]);

        return true;
    }
}
