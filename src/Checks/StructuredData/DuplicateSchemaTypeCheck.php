<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\StructuredData;

use Dashed\DashedSeo\Checks\BaseCheck;
use Dashed\DashedSeo\Models\SiteAudit;

/**
 * Twee topknopen van dezelfde familie op een pagina. Alleen topknopen: een
 * Product met honderd ListItems of Offers erin is geen dubbel schema.
 */
class DuplicateSchemaTypeCheck extends BaseCheck
{
    use StructuredDataPages;

    public function key(): string
    {
        return 'duplicate_schema_type';
    }

    public function run(SiteAudit $audit): iterable
    {
        foreach ($this->scannedPages($audit) as $page) {
            $duplicates = array_filter($this->familyCounts($page), fn (int $count) => $count > 1);

            if ($duplicates !== []) {
                $types = [];
                foreach ($duplicates as $family => $count) {
                    $types[] = "{$family} ({$count}x)";
                }

                yield $this->finding($page, ['types' => implode(', ', $types)]);
            }
        }
    }
}
