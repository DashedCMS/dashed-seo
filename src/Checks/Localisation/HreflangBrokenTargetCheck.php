<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\Localisation;

use Dashed\DashedSeo\Models\SiteAudit;
use Dashed\DashedSeo\Checks\BaseCheck;
use Dashed\DashedSeo\Checks\Support\HreflangGraph;

class HreflangBrokenTargetCheck extends BaseCheck
{
    public function key(): string
    {
        return 'hreflang_broken_target';
    }

    public function run(SiteAudit $audit): iterable
    {
        $graph = HreflangGraph::build($audit);

        foreach ($graph->pagesWithHreflang() as $page) {
            foreach ($graph->entriesFor($page->id) as $entry) {
                $target = $graph->pageByHash($entry['target_hash']);

                if ($target !== null && $target->status_code !== 200) {
                    yield $this->finding($page, [
                        'target' => $entry['target'],
                        'status_code' => $target->status_code,
                    ]);
                }
            }
        }
    }
}
