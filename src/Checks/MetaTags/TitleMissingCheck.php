<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\MetaTags;

use Dashed\DashedSeo\Models\SiteAudit;
use Dashed\DashedSeo\Checks\BaseCheck;

class TitleMissingCheck extends BaseCheck
{
    public function key(): string
    {
        return 'title_missing';
    }

    public function run(SiteAudit $audit): iterable
    {
        $pages = $this->pages($audit)
            ->where('status_code', 200)
            ->where(fn ($query) => $query->whereNull('title')->orWhere('title', ''))
            ->orderBy('id');

        foreach ($pages->cursor() as $page) {
            yield $this->finding($page);
        }
    }
}
