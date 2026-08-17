<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteAuditPage extends Model
{
    public const CRAWL_PENDING = 'pending';
    public const CRAWL_CRAWLING = 'crawling';
    public const CRAWL_DONE = 'done';
    public const CRAWL_ERROR = 'error';

    protected $table = 'dashed__site_audit_pages';

    protected $guarded = [];

    protected $casts = [
        'in_sitemap' => 'boolean',
        'from_cms' => 'boolean',
        'blocked_by_robots' => 'boolean',
        'indexable' => 'boolean',
        'redirect_chain' => 'array',
        'h1s' => 'array',
        'heading_counts' => 'array',
        'hreflangs' => 'array',
        'images' => 'array',
        'stylesheets' => 'array',
        'scripts' => 'array',
        'icons' => 'array',
        'crawled_at' => 'datetime',
    ];

    public function audit(): BelongsTo
    {
        return $this->belongsTo(SiteAudit::class, 'audit_id');
    }

    public function findings(): HasMany
    {
        return $this->hasMany(SiteAuditFinding::class, 'page_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('crawl_status', self::CRAWL_PENDING);
    }
}
