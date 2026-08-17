<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\Redirects;

use Dashed\DashedSeo\Checks\BaseCheck;
use Dashed\DashedSeo\Models\SiteAudit;
use Dashed\DashedSeo\Checks\Support\RedirectWalker;

class Status3xxCheck extends BaseCheck
{
    public function key(): string
    {
        return 'status_3xx';
    }

    public function run(SiteAudit $audit): iterable
    {
        foreach ($this->pages($audit)->whereBetween('status_code', [300, 399])->orderBy('id')->cursor() as $page) {
            $walk = RedirectWalker::walk($audit, $page);
            $page->update(['redirect_chain' => $walk['chain']]);

            yield $this->finding($page, [
                'status_code' => $page->status_code,
                'target' => $page->redirect_target,
                'hops' => $walk['hops'],
            ]);
        }
    }
}
