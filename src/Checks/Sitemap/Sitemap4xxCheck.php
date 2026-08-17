<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\Sitemap;

use Dashed\DashedSeo\Checks\BaseCheck;
use Dashed\DashedSeo\Models\SiteAudit;

class Sitemap4xxCheck extends BaseCheck
{
    public function key(): string
    {
        return 'sitemap_4xx';
    }

    public function run(SiteAudit $audit): iterable
    {
        $pages = $this->pages($audit)->where('in_sitemap', true)->whereBetween('status_code', [400, 499])->orderBy('id');

        foreach ($pages->cursor() as $page) {
            yield $this->finding($page, ['status_code' => $page->status_code]);
        }
    }
}
