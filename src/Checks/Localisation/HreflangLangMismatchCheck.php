<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\Localisation;

use Dashed\DashedSeo\Models\SiteAudit;
use Dashed\DashedSeo\Checks\BaseCheck;
use Dashed\DashedSeo\Checks\Support\HreflangGraph;

class HreflangLangMismatchCheck extends BaseCheck
{
    public function key(): string
    {
        return 'hreflang_lang_mismatch';
    }

    public function run(SiteAudit $audit): iterable
    {
        $graph = HreflangGraph::build($audit);

        foreach ($graph->pagesWithHreflang() as $page) {
            if ($page->html_lang === null) {
                continue;
            }

            foreach ($graph->entriesFor($page->id) as $entry) {
                if ($entry['target_hash'] !== $page->url_hash || $entry['lang'] === 'x-default') {
                    continue;
                }

                $declared = strtolower(explode('-', $entry['lang'])[0]);
                $actual = strtolower(explode('-', $page->html_lang)[0]);

                if ($declared !== $actual) {
                    yield $this->finding($page, ['hreflang' => $entry['lang'], 'html_lang' => $page->html_lang]);
                }
            }
        }
    }
}
