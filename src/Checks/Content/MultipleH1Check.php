<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\Content;

use Dashed\DashedSeo\Models\SiteAudit;
use Dashed\DashedSeo\Checks\BaseCheck;

class MultipleH1Check extends BaseCheck
{
    public function key(): string
    {
        return 'multiple_h1';
    }

    public function run(SiteAudit $audit): iterable
    {
        foreach ($this->indexablePages($audit)->orderBy('id')->cursor() as $page) {
            $count = count($page->h1s ?? []);

            if ($count > 1) {
                yield $this->finding($page, ['count' => $count]);
            }
        }
    }
}
