<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\Assets;

use Dashed\DashedSeo\Models\SiteAudit;
use Dashed\DashedSeo\Checks\BaseCheck;

class MixedContentCheck extends BaseCheck
{
    public function key(): string
    {
        return 'mixed_content';
    }

    public function run(SiteAudit $audit): iterable
    {
        foreach ($this->pages($audit)->where('status_code', 200)->orderBy('id')->cursor() as $page) {
            if (! str_starts_with($page->url, 'https://')) {
                continue;
            }

            $resources = array_merge(
                $page->stylesheets ?? [],
                $page->scripts ?? [],
                array_column($page->images ?? [], 'src'),
            );

            $insecure = array_values(array_filter(
                $resources,
                fn (string $url) => str_starts_with($url, 'http://'),
            ));

            if ($insecure !== []) {
                yield $this->finding($page, ['resources' => $insecure]);
            }
        }
    }
}
