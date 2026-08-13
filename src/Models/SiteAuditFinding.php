<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteAuditFinding extends Model
{
    public $timestamps = false;

    protected $table = 'dashed__site_audit_findings';

    protected $guarded = [];

    protected $casts = [
        'context' => 'array',
        'is_new' => 'boolean',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(SiteAuditPage::class, 'page_id');
    }
}
