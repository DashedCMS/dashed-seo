<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\Sitemap;

use Dashed\DashedSeo\Checks\BaseCheck;
use Dashed\DashedSeo\Models\SiteAudit;

class SitemapRedirectCheck extends BaseCheck
{
    public function key(): string
    {
        return 'sitemap_redirect';
    }

    public function run(SiteAudit $audit): iterable
    {
        $pages = $this->pages($audit)->where('in_sitemap', true)->whereBetween('status_code', [300, 399])->orderBy('id');

        foreach ($pages->cursor() as $page) {
            yield $this->finding($page, ['target' => $page->redirect_target]);
        }
    }
}
