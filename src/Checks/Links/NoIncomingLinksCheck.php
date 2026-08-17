<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\Links;

use Dashed\DashedSeo\Checks\BaseCheck;
use Dashed\DashedSeo\Models\SiteAudit;
use Dashed\DashedSeo\Models\SiteAuditLink;
use Dashed\DashedSeo\Models\SiteAuditPage;

class NoIncomingLinksCheck extends BaseCheck
{
    public function key(): string
    {
        return 'no_incoming_links';
    }

    public function run(SiteAudit $audit): iterable
    {
        foreach ($this->indexablePages($audit)->orderBy('id')->cursor() as $page) {
            if ($this->incomingCount($audit, $page) === 0) {
                yield $this->finding($page);
            }
        }
    }

    protected function incomingCount(SiteAudit $audit, SiteAuditPage $page): int
    {
        return SiteAuditLink::where('audit_id', $audit->id)
            ->where('to_page_id', $page->id)
            ->where('from_page_id', '!=', $page->id)
            ->count();
    }
}
