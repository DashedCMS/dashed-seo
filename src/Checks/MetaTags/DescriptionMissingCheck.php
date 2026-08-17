<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\MetaTags;

use Dashed\DashedSeo\Checks\BaseCheck;
use Dashed\DashedSeo\Models\SiteAudit;

class DescriptionMissingCheck extends BaseCheck
{
    public function key(): string
    {
        return 'description_missing';
    }

    public function run(SiteAudit $audit): iterable
    {
        $pages = $this->indexablePages($audit)
            ->where(fn ($query) => $query->whereNull('meta_description')->orWhere('meta_description', ''))
            ->orderBy('id');

        foreach ($pages->cursor() as $page) {
            yield $this->finding($page);
        }
    }
}
