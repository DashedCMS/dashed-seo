<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\Localisation;

use Dashed\DashedSeo\Checks\BaseCheck;
use Dashed\DashedSeo\Models\SiteAudit;
use Dashed\DashedSeo\Checks\Support\HreflangGraph;

class HreflangInvalidCodeCheck extends BaseCheck
{
    /** ISO 639-1, eventueel met een landcode, of x-default. */
    public const PATTERN = '/^([a-z]{2}(-[a-zA-Z]{2})?|x-default)$/';

    public function key(): string
    {
        return 'hreflang_invalid_code';
    }

    public function run(SiteAudit $audit): iterable
    {
        $graph = HreflangGraph::build($audit);

        foreach ($graph->pagesWithHreflang() as $page) {
            foreach ($graph->entriesFor($page->id) as $entry) {
                if (preg_match(self::PATTERN, $entry['lang']) !== 1) {
                    yield $this->finding($page, ['lang' => $entry['lang'], 'target' => $entry['target']]);
                }
            }
        }
    }
}
