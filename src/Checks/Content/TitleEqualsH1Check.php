<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\Content;

use Dashed\DashedSeo\Checks\BaseCheck;
use Dashed\DashedSeo\Models\SiteAudit;

class TitleEqualsH1Check extends BaseCheck
{
    public function key(): string
    {
        return 'title_equals_h1';
    }

    public function run(SiteAudit $audit): iterable
    {
        foreach ($this->indexablePages($audit)->whereNotNull('title')->orderBy('id')->cursor() as $page) {
            foreach ($page->h1s ?? [] as $h1) {
                if (trim($h1) !== '' && trim($h1) === trim($page->title)) {
                    yield $this->finding($page, ['title' => $page->title]);

                    break;
                }
            }
        }
    }
}
