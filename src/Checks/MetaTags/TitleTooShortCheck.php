<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\MetaTags;

use Dashed\DashedSeo\Models\SiteAudit;
use Dashed\DashedSeo\Checks\BaseCheck;

class TitleTooShortCheck extends BaseCheck
{
    public function key(): string
    {
        return 'title_too_short';
    }

    public function run(SiteAudit $audit): iterable
    {
        $pages = $this->indexablePages($audit)->whereNotNull('title')->where('title', '!=', '')->orderBy('id');

        foreach ($pages->cursor() as $page) {
            $length = mb_strlen($page->title);

            if ($length < $this->threshold('title_min')) {
                yield $this->finding($page, ['length' => $length]);
            }
        }
    }
}
