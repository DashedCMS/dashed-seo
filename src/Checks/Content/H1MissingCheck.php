<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\Content;

use Dashed\DashedSeo\Checks\BaseCheck;
use Dashed\DashedSeo\Models\SiteAudit;

class H1MissingCheck extends BaseCheck
{
    public function key(): string
    {
        return 'h1_missing';
    }

    public function run(SiteAudit $audit): iterable
    {
        foreach ($this->indexablePages($audit)->orderBy('id')->cursor() as $page) {
            if (count($page->h1s ?? []) === 0) {
                yield $this->finding($page);
            }
        }
    }
}
