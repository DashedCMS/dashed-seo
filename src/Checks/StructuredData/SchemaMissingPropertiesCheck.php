<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\StructuredData;

use Dashed\DashedSeo\Checks\BaseCheck;
use Dashed\DashedSeo\Models\SiteAudit;
use Dashed\DashedSeo\Crawler\StructuredData;

/**
 * Per topknoop de verplichte eigenschappen van zijn familie
 * (dashed-seo.structured_data.required); een type zonder regel wordt niet
 * beoordeeld. Een bevinding per pagina met alle gaten in de context, zodat
 * de vergelijking met de vorige scan (op pagina en controle) blijft kloppen.
 */
class SchemaMissingPropertiesCheck extends BaseCheck
{
    use StructuredDataPages;

    public function key(): string
    {
        return 'schema_missing_properties';
    }

    public function run(SiteAudit $audit): iterable
    {
        $required = (array) config('dashed-seo.structured_data.required', []);

        foreach ($this->scannedPages($audit) as $page) {
            $missing = [];

            foreach ($page->structured_data as $block) {
                foreach ($block['nodes'] ?? [] as $node) {
                    $family = StructuredData::family((string) ($node['type'] ?? ''));
                    $gaps = array_diff((array) ($required[$family] ?? []), (array) ($node['properties'] ?? []));

                    if ($gaps !== []) {
                        $missing[] = ($node['type'] ?? $family) . ': ' . implode(', ', $gaps);
                    }
                }
            }

            if ($missing !== []) {
                yield $this->finding($page, ['count' => count($missing), 'missing' => implode('; ', array_unique($missing))]);
            }
        }
    }
}
