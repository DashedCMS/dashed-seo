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

    public function isRunning(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_CRAWLING, self::STATUS_ANALYSING], true);
    }
}
