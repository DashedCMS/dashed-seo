<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\Speed;

use Dashed\DashedSeo\Models\SiteAudit;
use Dashed\DashedSeo\Checks\BaseCheck;

class HtmlTooLargeCheck extends BaseCheck
{
    public function key(): string
    {
        return 'html_too_large';
    }

    public function run(SiteAudit $audit): iterable
    {
        $pages = $this->pages($audit)
            ->whereNotNull('html_size')
            ->where('html_size', '>', $this->threshold('html_max_bytes'))
            ->orderBy('id');

        foreach ($pages->cursor() as $page) {
            yield $this->finding($page, ['html_size' => $page->html_size]);
        }
    }
}
