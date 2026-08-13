<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\Redirects;

use Dashed\DashedSeo\Models\SiteAudit;
use Dashed\DashedSeo\Checks\BaseCheck;
use Dashed\DashedSeo\Checks\Support\RedirectWalker;

class RedirectLoopCheck extends BaseCheck
{
    public function key(): string
    {
        return 'redirect_loop';
    }

    public function run(SiteAudit $audit): iterable
    {
        foreach ($this->pages($audit)->whereBetween('status_code', [300, 399])->orderBy('id')->cursor() as $page) {
            $walk = RedirectWalker::walk($audit, $page);

            if ($walk['loop']) {
                yield $this->finding($page, ['chain' => $walk['chain']]);
            }
        }
    }
}
