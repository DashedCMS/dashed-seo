<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\MetaTags;

use Dashed\DashedSeo\Models\SiteAudit;
use Dashed\DashedSeo\Checks\BaseCheck;

class DescriptionTooLongCheck extends BaseCheck
{
    public function key(): string
    {
        return 'description_too_long';
    }

    public function run(SiteAudit $audit): iterable
    {
        foreach ($this->indexablePages($audit)->whereNotNull('meta_description')->orderBy('id')->cursor() as $page) {
            $length = mb_strlen($page->meta_description);

            if ($length > $this->threshold('description_max')) {
                yield $this->finding($page, ['length' => $length]);
            }
        }
    }
}
