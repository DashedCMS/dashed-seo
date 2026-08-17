<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\Links;

use Dashed\DashedSeo\Checks\BaseCheck;
use Dashed\DashedSeo\Models\SiteAudit;
use Dashed\DashedSeo\Models\SiteAuditLink;
use Dashed\DashedSeo\Models\SiteAuditPage;

class InternalNofollowLinkCheck extends BaseCheck
{
    public function key(): string
    {
        return 'internal_nofollow_link';
    }

    public function run(SiteAudit $audit): iterable
    {
        $links = SiteAuditLink::where('audit_id', $audit->id)
            ->where('is_external', false)
            ->where('rel', 'like', '%nofollow%')
            ->orderBy('from_page_id')
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
                yield $this->finding($page, ['target' => $link->to_url, 'anchor' => $link->anchor]);
            }
        }
    }
}
