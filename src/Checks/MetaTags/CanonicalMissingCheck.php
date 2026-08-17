<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\MetaTags;

use Dashed\DashedSeo\Checks\BaseCheck;
use Dashed\DashedSeo\Models\SiteAudit;

class CanonicalMissingCheck extends BaseCheck
{
    public function key(): string
    {
        return 'canonical_missing';
    }

    public function run(SiteAudit $audit): iterable
    {
        $pages = $this->indexablePages($audit)
            ->where(fn ($query) => $query->whereNull('canonical')->orWhere('canonical', ''))
            ->orderBy('id');

        foreach ($pages->cursor() as $page) {
            yield $this->finding($page);
        }
    }
}
