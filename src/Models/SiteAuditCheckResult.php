<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Models;

use Illuminate\Database\Eloquent\Model;

class SiteAuditCheckResult extends Model
{
    public $timestamps = false;

    protected $table = 'dashed__site_audit_check_results';

    protected $guarded = [];
}
