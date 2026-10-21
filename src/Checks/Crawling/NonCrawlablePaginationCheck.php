<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\Crawling;

use Dashed\DashedSeo\Checks\BaseCheck;
use Dashed\DashedSeo\Models\SiteAudit;

/**
 * De oude themapartial pagineerde met <span wire:click="gotoPage(2)">; de
 * pakketpartial (dashed-ecommerce-core::frontend.pagination) zet dezelfde
 * klik op een anker met href. De parser zet het vlaggetje, de controle
 * meldt het per pagina, zodat een klantthema dat de partial nog niet heeft
 * vervangen in de scan opvalt.
 */
class NonCrawlablePaginationCheck extends BaseCheck
{
    public function key(): string
    {
        return 'non_crawlable_pagination';
    }

    public function run(SiteAudit $audit): iterable
    {
        foreach ($this->indexablePages($audit)->where('non_crawlable_pagination', true)->orderBy('id')->cursor() as $page) {
            yield $this->finding($page);
        }
    }
}
