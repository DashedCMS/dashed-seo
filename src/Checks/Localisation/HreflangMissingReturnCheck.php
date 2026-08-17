<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\Localisation;

use Dashed\DashedSeo\Checks\BaseCheck;
use Dashed\DashedSeo\Models\SiteAudit;
use Dashed\DashedSeo\Checks\Support\HreflangGraph;

class HreflangMissingReturnCheck extends BaseCheck
{
    public function key(): string
    {
        return 'hreflang_missing_return';
    }

    public function run(SiteAudit $audit): iterable
    {
        $graph = HreflangGraph::build($audit);

        foreach ($graph->pagesWithHreflang() as $page) {
            foreach ($graph->entriesFor($page->id) as $entry) {
                if ($entry['target_hash'] === $page->url_hash) {
                    continue;
                }

                $target = $graph->pageByHash($entry['target_hash']);
                if ($target === null) {
                    continue;
                }

                if (! $graph->declaresTarget($target->id, $page->url_hash)) {
                    yield $this->finding($page, ['target' => $entry['target']]);
                }
            }
        }
    }
}
