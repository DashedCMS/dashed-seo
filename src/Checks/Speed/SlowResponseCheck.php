<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\Speed;

use Dashed\DashedSeo\Checks\BaseCheck;
use Dashed\DashedSeo\Models\SiteAudit;

class SlowResponseCheck extends BaseCheck
{
    public function key(): string
    {
        return 'slow_response';
    }

    public function run(SiteAudit $audit): iterable
    {
        $pages = $this->pages($audit)
            ->whereNotNull('response_time_ms')
            ->where('response_time_ms', '>', $this->threshold('slow_response_ms'))
            ->orderBy('id');

        foreach ($pages->cursor() as $page) {
            yield $this->finding($page, ['response_time_ms' => $page->response_time_ms]);
        }
    }
}
