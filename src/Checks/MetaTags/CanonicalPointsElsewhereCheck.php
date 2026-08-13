<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\MetaTags;

use Dashed\DashedSeo\Models\SiteAudit;
use Dashed\DashedSeo\Checks\BaseCheck;
use Dashed\DashedSeo\Crawler\UrlNormalizer;

class CanonicalPointsElsewhereCheck extends BaseCheck
{
    public function key(): string
    {
        return 'canonical_points_elsewhere';
    }

    public function run(SiteAudit $audit): iterable
    {
        foreach ($this->indexablePages($audit)->whereNotNull('canonical')->orderBy('id')->cursor() as $page) {
            $canonical = UrlNormalizer::normalize($page->canonical, $audit->base_url);

            if ($canonical !== null && UrlNormalizer::hash($canonical) !== $page->url_hash) {
                yield $this->finding($page, ['canonical' => $canonical]);
            }
        }
    }
}
