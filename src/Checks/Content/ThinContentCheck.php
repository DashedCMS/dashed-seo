<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\Content;

use Dashed\DashedSeo\Models\SiteAudit;
use Dashed\DashedSeo\Checks\BaseCheck;

class ThinContentCheck extends BaseCheck
{
    public function key(): string
    {
        return 'thin_content';
    }

    public function run(SiteAudit $audit): iterable
    {
        $pages = $this->indexablePages($audit)
            ->whereNotNull('word_count')
            ->where('word_count', '<', $this->threshold('min_words'))
            ->orderBy('id');

        foreach ($pages->cursor() as $page) {
            yield $this->finding($page, ['word_count' => $page->word_count]);
        }
    }
}
