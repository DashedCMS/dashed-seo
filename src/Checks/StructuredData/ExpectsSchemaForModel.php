<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\StructuredData;

use Dashed\DashedSeo\Models\SiteAudit;

/**
 * Een bevinding per pagina waarvan het CMS-model een van de gegeven
 * schemafamilies verwacht (dashed-seo.structured_data.expected) terwijl
 * geen daarvan op de pagina staat.
 */
trait ExpectsSchemaForModel
{
    protected function missingForModels(SiteAudit $audit, array $families): iterable
    {
        $expected = (array) config('dashed-seo.structured_data.expected', []);
        $models = array_keys(array_filter(
            $expected,
            fn (array $types) => array_intersect(array_map('strtolower', $types), $families) !== [],
        ));

        if ($models === []) {
            return;
        }

        foreach ($this->scannedPages($audit) as $page) {
            if (! in_array((string) $page->model_type, $models, true)) {
                continue;
            }

            $wanted = array_map('strtolower', (array) $expected[$page->model_type]);

            if (array_intersect_key(array_flip($wanted), $this->familyCounts($page)) === []) {
                yield $this->finding($page, ['model' => class_basename((string) $page->model_type), 'expected' => implode(' of ', $wanted)]);
            }
        }
    }
}
