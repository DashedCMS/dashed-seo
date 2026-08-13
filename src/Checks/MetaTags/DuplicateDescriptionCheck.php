<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\MetaTags;

use Dashed\DashedSeo\Models\SiteAudit;
use Dashed\DashedSeo\Checks\BaseCheck;

class DuplicateDescriptionCheck extends BaseCheck
{
    public function key(): string
    {
        return 'duplicate_description';
    }

    public function run(SiteAudit $audit): iterable
    {
        $duplicates = $this->indexablePages($audit)
            ->whereNotNull('meta_description')
            ->where('meta_description', '!=', '')
            ->select('meta_description')
            ->groupBy('meta_description')
            ->havingRaw('count(*) > 1')
            ->pluck('meta_description');

        if ($duplicates->isEmpty()) {
            return;
        }

        foreach ($this->indexablePages($audit)->whereIn('meta_description', $duplicates)->orderBy('id')->cursor() as $page) {
            yield $this->finding($page, ['description' => $page->meta_description]);
        }
    }
}
