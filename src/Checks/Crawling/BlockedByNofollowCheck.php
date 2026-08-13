<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\Crawling;

use Dashed\DashedSeo\Models\SiteAudit;
use Dashed\DashedSeo\Checks\BaseCheck;
use Dashed\DashedSeo\Models\SiteAuditPage;

class BlockedByNofollowCheck extends BaseCheck
{
    public function key(): string
    {
        return 'blocked_by_nofollow';
    }

    public function run(SiteAudit $audit): iterable
    {
        foreach ($this->pages($audit)->where('robots_meta', 'like', '%nofollow%')->orderBy('id')->cursor() as $page) {
            yield $this->finding($page, ['robots' => $page->robots_meta]);
        }
    }
}
