<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\Crawling;

use Dashed\DashedSeo\Models\SiteAudit;
use Dashed\DashedSeo\Checks\BaseCheck;
use Dashed\DashedSeo\Models\SiteAuditPage;

class Status4xxCheck extends BaseCheck
{
    public function key(): string
    {
        return 'status_4xx';
    }

    public function run(SiteAudit $audit): iterable
    {
        foreach ($this->pages($audit)->whereBetween('status_code', [400, 499])->orderBy('id')->cursor() as $page) {
            yield $this->finding($page, ['status_code' => $page->status_code]);
        }
    }
}
