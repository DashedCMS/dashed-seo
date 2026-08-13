<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\Content;

use Dashed\DashedSeo\Models\SiteAudit;
use Dashed\DashedSeo\Checks\BaseCheck;

class H1EmptyCheck extends BaseCheck
{
    public function key(): string
    {
        return 'h1_empty';
    }

    public function run(SiteAudit $audit): iterable
    {
        foreach ($this->indexablePages($audit)->orderBy('id')->cursor() as $page) {
            $h1s = $page->h1s ?? [];

            if ($h1s === []) {
                continue;
            }

            foreach ($h1s as $h1) {
                if (trim($h1) === '') {
                    yield $this->finding($page);

                    break;
                }
            }
        }
    }
}
