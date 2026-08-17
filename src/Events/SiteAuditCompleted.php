<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Events;

use Dashed\DashedSeo\Models\SiteAudit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Een scan is afgerond en het rapport staat klaar. Dashed-seo weet niet waar de
 * meldingen heen gaan; wie er iets mee wil (de app, een mail) luistert hierop.
 */
class SiteAuditCompleted
{
    use Dispatchable;

    public function __construct(public SiteAudit $audit)
    {
    }
}
