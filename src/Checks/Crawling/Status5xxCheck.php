<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\Crawling;

use Dashed\DashedSeo\Checks\BaseCheck;
use Dashed\DashedSeo\Models\SiteAudit;

class Status5xxCheck extends BaseCheck
{
    public function key(): string
    {
        return 'status_5xx';
    }

    public function run(SiteAudit $audit): iterable
    {
        foreach ($this->pages($audit)->whereBetween('status_code', [500, 599])->orderBy('id')->cursor() as $page) {
            yield $this->finding($page, ['status_code' => $page->status_code]);
        }
    }
}
