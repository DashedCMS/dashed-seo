<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\Sitemap;

use Dashed\DashedSeo\Checks\BaseCheck;
use Dashed\DashedSeo\Models\SiteAudit;

class SitemapNotInRobotsCheck extends BaseCheck
{
    public function key(): string
    {
        return 'sitemap_not_in_robots';
    }

    public function run(SiteAudit $audit): iterable
    {
        if (($audit->settings['robots_has_sitemap'] ?? true) === false) {
            yield $this->siteFinding(['robots_url' => rtrim($audit->base_url, '/') . '/robots.txt']);
        }
    }
}
