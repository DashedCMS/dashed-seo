<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\Links;

use Dashed\DashedSeo\Models\SiteAudit;
use Dashed\DashedSeo\Checks\BaseCheck;
use Dashed\DashedSeo\Models\SiteAuditLink;
use Dashed\DashedSeo\Models\SiteAuditPage;
use Dashed\DashedSeo\Models\SiteAuditAsset;

class BrokenExternalLinkCheck extends BaseCheck
{
    public function key(): string
    {
        return 'broken_external_link';
    }

    public function run(SiteAudit $audit): iterable
    {
        $broken = SiteAuditAsset::where('audit_id', $audit->id)
            ->where('type', SiteAuditAsset::TYPE_EXTERNAL)
            ->where(fn ($query) => $query->whereNull('status_code')->orWhere('status_code', '>=', 400))
            ->pluck('url_hash')
            ->flip();

        if ($broken->isEmpty()) {
            return;
        }

        $links = SiteAuditLink::where('audit_id', $audit->id)
            ->where('is_external', true)
            ->whereIn('to_url_hash', $broken->keys())
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
