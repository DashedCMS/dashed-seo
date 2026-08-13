<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\Crawling;

use Dashed\DashedSeo\Models\SiteAudit;
use Dashed\DashedSeo\Checks\BaseCheck;
use Dashed\DashedSeo\Models\SiteAuditPage;

class BlockedByRobotsCheck extends BaseCheck
{
    public function key(): string
    {
        return 'blocked_by_robots';
    }

    public function run(SiteAudit $audit): iterable
    {
        foreach ($audit->pages()->getQuery()->where('blocked_by_robots', true)->orderBy('id')->cursor() as $page) {
            yield $this->finding($page, ['path' => $page->path]);
        }
    }
}
