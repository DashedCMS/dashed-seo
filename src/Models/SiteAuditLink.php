<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteAuditLink extends Model
{
    public $timestamps = false;

    protected $table = 'dashed__site_audit_links';

    protected $guarded = [];

    protected $casts = [
        'is_external' => 'boolean',
        'is_image_link' => 'boolean',
    ];

    public function fromPage(): BelongsTo
    {
        return $this->belongsTo(SiteAuditPage::class, 'from_page_id');
    }

    public function toPage(): BelongsTo
    {
        return $this->belongsTo(SiteAuditPage::class, 'to_page_id');
    }
}
