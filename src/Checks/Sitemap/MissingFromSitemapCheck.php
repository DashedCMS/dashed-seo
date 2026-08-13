<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\Sitemap;

use Dashed\DashedSeo\Models\SiteAudit;
use Dashed\DashedSeo\Checks\BaseCheck;

class MissingFromSitemapCheck extends BaseCheck
{
    public function key(): string
    {
        return 'missing_from_sitemap';
    }

    public function run(SiteAudit $audit): iterable
    {
        foreach ($this->indexablePages($audit)->where('in_sitemap', false)->orderBy('id')->cursor() as $page) {
            yield $this->finding($page);
        }
    }
}
