<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\Crawling;

use Dashed\DashedSeo\Checks\BaseCheck;
use Dashed\DashedSeo\Models\SiteAudit;
use Dashed\DashedSeo\Models\SiteAuditPage;

class UnreachableCheck extends BaseCheck
{
    public function key(): string
    {
        return 'unreachable';
    }

    public function run(SiteAudit $audit): iterable
    {
        $pages = $audit->pages()->getQuery()
            ->where('crawl_status', SiteAuditPage::CRAWL_ERROR)
            ->orderBy('id');

        foreach ($pages->cursor() as $page) {
            yield $this->finding($page, ['fetch_error' => $page->fetch_error]);
        }
    }
}
