<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\MetaTags;

use Dashed\DashedSeo\Models\SiteAudit;
use Dashed\DashedSeo\Checks\BaseCheck;

class DuplicateTitleCheck extends BaseCheck
{
    public function key(): string
    {
        return 'duplicate_title';
    }

    public function run(SiteAudit $audit): iterable
    {
        $duplicates = $this->indexablePages($audit)
            ->whereNotNull('title')
            ->where('title', '!=', '')
            ->select('title')
            ->groupBy('title')
            ->havingRaw('count(*) > 1')
            ->pluck('title');

        if ($duplicates->isEmpty()) {
            return;
        }

        foreach ($this->indexablePages($audit)->whereIn('title', $duplicates)->orderBy('id')->cursor() as $page) {
            yield $this->finding($page, ['title' => $page->title]);
        }
    }
}
