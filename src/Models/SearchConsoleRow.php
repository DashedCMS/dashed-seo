<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Models;

use Illuminate\Database\Eloquent\Model;

class SearchConsoleRow extends Model
{
    public $timestamps = false;

    protected $table = 'dashed__search_console_rows';

    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
        'clicks' => 'integer',
        'impressions' => 'integer',
        'ctr' => 'float',
        'position' => 'float',
    ];
}
