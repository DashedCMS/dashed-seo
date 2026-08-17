<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\Speed;

use Dashed\DashedSeo\Checks\BaseCheck;
use Dashed\DashedSeo\Models\SiteAudit;

class NoCompressionCheck extends BaseCheck
{
    public function key(): string
    {
        return 'no_compression';
    }

    public function run(SiteAudit $audit): iterable
    {
        $pages = $this->pages($audit)
            ->where('status_code', 200)
            ->where(fn ($query) => $query->whereNull('content_encoding')->orWhere('content_encoding', ''))
            ->orderBy('id');

        foreach ($pages->cursor() as $page) {
            yield $this->finding($page);
        }
    }
}
