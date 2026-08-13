<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Models;

use Illuminate\Database\Eloquent\Model;

class SiteAuditAsset extends Model
{
    public const TYPE_IMAGE = 'image';
    public const TYPE_CSS = 'css';
    public const TYPE_JS = 'js';
    public const TYPE_EXTERNAL = 'external';

    public $timestamps = false;

    protected $table = 'dashed__site_audit_assets';

    protected $guarded = [];

    protected $casts = [
        'compressed' => 'boolean',
    ];
}
