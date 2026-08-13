<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\Links;

use Dashed\DashedSeo\Models\SiteAudit;
use Dashed\DashedSeo\Checks\BaseCheck;
use Dashed\DashedSeo\Models\SiteAuditLink;

class SingleIncomingLinkCheck extends BaseCheck
{
    public function key(): string
    {
        return 'single_incoming_link';
    }

    public function run(SiteAudit $audit): iterable
    {
        foreach ($this->indexablePages($audit)->orderBy('id')->cursor() as $page) {
            $count = SiteAuditLink::where('audit_id', $audit->id)
                ->where('to_page_id', $page->id)
                ->where('from_page_id', '!=', $page->id)
                ->count();

            if ($count === 1) {
                yield $this->finding($page, ['incoming' => 1]);
            }
        }
    }
}
