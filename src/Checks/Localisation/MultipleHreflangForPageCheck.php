<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\Localisation;

use Dashed\DashedSeo\Checks\BaseCheck;
use Dashed\DashedSeo\Models\SiteAudit;
use Dashed\DashedSeo\Checks\Support\HreflangGraph;

class MultipleHreflangForPageCheck extends BaseCheck
{
    public function key(): string
    {
        return 'multiple_hreflang_for_page';
    }

    public function run(SiteAudit $audit): iterable
    {
        $graph = HreflangGraph::build($audit);

        foreach ($graph->langsPerTarget() as $hash => $langs) {
            $meaningful = array_values(array_filter($langs, fn (string $lang) => $lang !== 'x-default'));

            if (count($meaningful) < 2) {
                continue;
            }

            $page = $graph->pageByHash($hash);
            if ($page === null) {
                continue;
            }

            yield $this->finding($page, ['langs' => $meaningful]);
        }
    }
}
