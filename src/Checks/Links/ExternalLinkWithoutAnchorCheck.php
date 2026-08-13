<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\Links;

use Dashed\DashedSeo\Models\SiteAudit;
use Dashed\DashedSeo\Checks\BaseCheck;
use Dashed\DashedSeo\Models\SiteAuditLink;
use Dashed\DashedSeo\Models\SiteAuditPage;

class ExternalLinkWithoutAnchorCheck extends BaseCheck
{
    public function key(): string
    {
        return 'external_link_without_anchor';
    }

    public function run(SiteAudit $audit): iterable
    {
        $links = SiteAuditLink::where('audit_id', $audit->id)
            ->where('is_external', true)
            ->where(fn ($query) => $query->whereNull('anchor')->orWhere('anchor', ''))
            ->orderBy('from_page_id')
            ->get();

        foreach ($links as $link) {
            $page = SiteAuditPage::find($link->from_page_id);

            if ($page !== null) {
                yield $this->finding($page, ['target' => $link->to_url]);
            }
        }
    }
}
