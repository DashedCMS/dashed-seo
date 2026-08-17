<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\Localisation;

use Dashed\DashedSeo\Checks\BaseCheck;
use Dashed\DashedSeo\Models\SiteAudit;
use Dashed\DashedSeo\Checks\Support\HreflangGraph;

class HreflangNoSelfReferenceCheck extends BaseCheck
{
    public function key(): string
    {
        return 'hreflang_no_self_reference';
    }

    public function run(SiteAudit $audit): iterable
    {
        $graph = HreflangGraph::build($audit);

        foreach ($graph->pagesWithHreflang() as $page) {
            if (! $graph->declaresTarget($page->id, $page->url_hash)) {
                yield $this->finding($page);
            }
        }
    }
}
