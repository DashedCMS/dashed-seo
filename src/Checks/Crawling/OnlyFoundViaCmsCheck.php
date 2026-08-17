<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\Crawling;

use Dashed\DashedSeo\Checks\BaseCheck;
use Dashed\DashedSeo\Models\SiteAudit;
use Dashed\DashedSeo\Models\SiteAuditLink;
use Dashed\DashedSeo\Models\SiteAuditPage;

/**
 * Pagina's die alleen bestaan omdat het CMS ze kent: niet in de sitemap, en er
 * linkt niets naartoe. Een bezoeker en een zoekmachine komen er dus nooit
 * vanzelf op uit. Zonder deze controle zou zo'n pagina helemaal buiten de scan
 * vallen en dus ook niet opvallen.
 */
class OnlyFoundViaCmsCheck extends BaseCheck
{
    public function key(): string
    {
        return 'only_found_via_cms';
    }

    public function run(SiteAudit $audit): iterable
    {
        $pages = $this->indexablePages($audit)
            ->where('from_cms', true)
            ->where('in_sitemap', false)
            ->orderBy('id');

        foreach ($pages->cursor() as $page) {
            if ($this->incomingCount($audit, $page) > 0) {
                continue;
            }

            yield $this->finding($page);
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
