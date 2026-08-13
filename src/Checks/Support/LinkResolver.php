<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\Support;

use Dashed\DashedSeo\Models\SiteAudit;
use Dashed\DashedSeo\Models\SiteAuditLink;
use Dashed\DashedSeo\Models\SiteAuditPage;

class LinkResolver
{
    public function __invoke(SiteAudit $audit): void
    {
        self::resolve($audit);
    }

    public static function resolve(SiteAudit $audit): void
    {
        SiteAuditPage::where('audit_id', $audit->id)
            ->select(['id', 'url_hash'])
            ->orderBy('id')
            ->chunk(500, function ($pages) use ($audit) {
                foreach ($pages as $page) {
                    SiteAuditLink::where('audit_id', $audit->id)
                        ->where('to_url_hash', $page->url_hash)
                        ->update(['to_page_id' => $page->id]);
                }
            });
    }
}
