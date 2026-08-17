<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\Links;

use Dashed\DashedSeo\Checks\BaseCheck;
use Dashed\DashedSeo\Models\SiteAudit;
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

        // Twee ankerloze links naar hetzelfde doel zijn een ding om te repareren:
        // welke ankertekst ontbreekt, niet hoe vaak hij ontbreekt.
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
