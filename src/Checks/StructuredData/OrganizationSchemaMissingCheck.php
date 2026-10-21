<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\StructuredData;

use Dashed\DashedSeo\Checks\BaseCheck;
use Dashed\DashedSeo\Models\SiteAudit;

/**
 * FrontendMiddleware zet Organization en WebSite op elke pagina; een pagina
 * zonder een van beide rendert buiten de head-component om, of een thema
 * heeft ze leeggemaakt.
 */
class OrganizationSchemaMissingCheck extends BaseCheck
{
    use StructuredDataPages;

    public function key(): string
    {
        return 'organization_schema_missing';
    }

    public function run(SiteAudit $audit): iterable
    {
        foreach ($this->scannedPages($audit) as $page) {
            $counts = $this->familyCounts($page);
            $missing = [];

            if (! isset($counts['organization'])) {
                $missing[] = 'Organization';
            }

            if (! isset($counts['website'])) {
                $missing[] = 'WebSite';
            }

            if ($missing !== []) {
                yield $this->finding($page, ['missing' => implode(', ', $missing)]);
            }
        }
    }
}
