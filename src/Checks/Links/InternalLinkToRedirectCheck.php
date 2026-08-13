<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\Links;

use Dashed\DashedSeo\Models\SiteAudit;
use Dashed\DashedSeo\Checks\BaseCheck;
use Dashed\DashedSeo\Models\SiteAuditLink;
use Dashed\DashedSeo\Models\SiteAuditPage;

class InternalLinkToRedirectCheck extends BaseCheck
{
    public function key(): string
    {
        return 'internal_link_to_redirect';
    }

    public function run(SiteAudit $audit): iterable
    {
        $links = SiteAuditLink::where('dashed__site_audit_links.audit_id', $audit->id)
            ->where('is_external', false)
            ->join('dashed__site_audit_pages', 'dashed__site_audit_pages.id', '=', 'dashed__site_audit_links.to_page_id')
            ->whereBetween('dashed__site_audit_pages.status_code', [300, 399])
            ->orderBy('dashed__site_audit_links.from_page_id')
            ->select(['dashed__site_audit_links.from_page_id', 'dashed__site_audit_links.to_url', 'dashed__site_audit_links.to_url_hash'])
            ->get();

        // Twee links vanaf dezelfde pagina naar hetzelfde doel zijn een ding om te
        // repareren, dus een dubbele melding op dat paar wordt overgeslagen.
        $seen = [];

        foreach ($links as $link) {
            $dedupeKey = $link->from_page_id . '|' . $link->to_url_hash;
            if (isset($seen[$dedupeKey])) {
                continue;
            }
            $seen[$dedupeKey] = true;

            $page = SiteAuditPage::find($link->from_page_id);

            if ($page !== null) {
                yield $this->finding($page, ['target' => $link->to_url]);
            }
        }
    }
}
