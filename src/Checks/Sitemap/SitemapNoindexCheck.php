<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\Sitemap;

use Dashed\DashedSeo\Checks\BaseCheck;
use Dashed\DashedSeo\Models\SiteAudit;

class SitemapNoindexCheck extends BaseCheck
{
    public function key(): string
    {
        return 'sitemap_noindex';
    }

    public function run(SiteAudit $audit): iterable
    {
        $pages = $this->pages($audit)->where('in_sitemap', true)->where('robots_meta', 'like', '%noindex%')->orderBy('id');

        foreach ($pages->cursor() as $page) {
            yield $this->finding($page, ['robots' => $page->robots_meta]);
        }
    }
}
