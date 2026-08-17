<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\Content;

use Dashed\DashedSeo\Checks\BaseCheck;
use Dashed\DashedSeo\Models\SiteAudit;

class MissingAltCheck extends BaseCheck
{
    public function key(): string
    {
        return 'missing_alt';
    }

    public function run(SiteAudit $audit): iterable
    {
        foreach ($this->indexablePages($audit)->orderBy('id')->cursor() as $page) {
            // Een lege alt is een bewuste keuze bij een decoratieve afbeelding.
            // Alleen een ontbrekend alt-attribuut is een probleem.
            $missing = array_values(array_filter(
                $page->images ?? [],
                fn (array $image) => ($image['alt'] ?? null) === null,
            ));

            if ($missing !== []) {
                yield $this->finding($page, [
                    'count' => count($missing),
                    'images' => array_column($missing, 'src'),
                ]);
            }
        }
    }
}
