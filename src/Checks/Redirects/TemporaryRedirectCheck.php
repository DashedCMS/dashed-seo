<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\Redirects;

use Dashed\DashedSeo\Checks\BaseCheck;
use Dashed\DashedSeo\Models\SiteAudit;

class TemporaryRedirectCheck extends BaseCheck
{
    public function key(): string
    {
        return 'temporary_redirect';
    }

    public function run(SiteAudit $audit): iterable
    {
        foreach ($this->pages($audit)->whereIn('status_code', [302, 303, 307])->orderBy('id')->cursor() as $page) {
            yield $this->finding($page, ['status_code' => $page->status_code, 'target' => $page->redirect_target]);
        }
    }
}
