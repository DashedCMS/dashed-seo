<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\Localisation;

use Dashed\DashedSeo\Checks\BaseCheck;
use Dashed\DashedSeo\Models\SiteAudit;
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

        // Alle deelnemende pagina's, niet alleen de pagina die zichzelf noemt: een
        // hreflang die vanaf een andere pagina naar hier wijst hoort net zo goed te
        // kloppen met het lang-attribuut van de pagina waar hij op uitkomt.
        $seen = [];

        foreach ($graph->pagesWithHreflang() as $page) {
            foreach ($graph->entriesFor($page->id) as $entry) {
                if ($entry['lang'] === 'x-default') {
                    continue;
                }

                $target = $graph->pageByHash($entry['target_hash']);
                if ($target === null || $target->html_lang === null) {
                    continue;
                }

                $declared = strtolower(explode('-', $entry['lang'])[0]);
                $actual = strtolower(explode('-', $target->html_lang)[0]);

                if ($declared === $actual) {
                    continue;
                }

                // Eenzelfde doelpagina en dezelfde verkeerde taalcode zijn een en
                // dezelfde fout, of die nu van de pagina zelf komt of van een andere
                // pagina die ernaar verwijst.
                $dedupeKey = $target->id . '|' . $declared;
                if (isset($seen[$dedupeKey])) {
                    continue;
                }
                $seen[$dedupeKey] = true;

                yield $this->finding($target, ['hreflang' => $entry['lang'], 'html_lang' => $target->html_lang]);
            }
        }
    }
}
