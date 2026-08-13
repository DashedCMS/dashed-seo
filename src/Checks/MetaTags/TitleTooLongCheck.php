<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\MetaTags;

use Dashed\DashedSeo\Models\SiteAudit;
use Dashed\DashedSeo\Checks\BaseCheck;

class TitleTooLongCheck extends BaseCheck
{
    public function key(): string
    {
        return 'title_too_long';
    }

    public function run(SiteAudit $audit): iterable
    {
        foreach ($this->indexablePages($audit)->whereNotNull('title')->orderBy('id')->cursor() as $page) {
            $length = mb_strlen($page->title);

            if ($length > $this->threshold('title_max')) {
                yield $this->finding($page, ['length' => $length]);
            }
        }
    }
}
