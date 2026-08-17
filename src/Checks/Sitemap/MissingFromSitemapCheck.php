<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\Sitemap;

use Dashed\DashedSeo\Checks\BaseCheck;
use Dashed\DashedSeo\Models\SiteAudit;

class MissingFromSitemapCheck extends BaseCheck
{
    public function key(): string
    {
        return 'missing_from_sitemap';
    }

    public function run(SiteAudit $audit): iterable
    {
        // Een pagina kan alleen ontbreken uit een sitemap die bestaat. Zonder
        // deze wacht zou elke indexeerbare pagina op een site zonder sitemap
        // hier verschijnen, terwijl er niets ontbreekt: er is niets om aan te
        // ontbreken.
        if ((int) ($audit->settings['sitemap_url_count'] ?? 0) === 0) {
            return;
        }

        foreach ($this->indexablePages($audit)->where('in_sitemap', false)->orderBy('id')->cursor() as $page) {
            yield $this->finding($page);
        }
    }
}
