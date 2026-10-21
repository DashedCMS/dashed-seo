<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\StructuredData;

use Dashed\DashedSeo\Checks\BaseCheck;
use Dashed\DashedSeo\Models\SiteAudit;

class StructuredDataInvalidCheck extends BaseCheck
{
    use StructuredDataPages;

    public function key(): string
    {
        return 'structured_data_invalid';
    }

    public function run(SiteAudit $audit): iterable
    {
        foreach ($this->scannedPages($audit) as $page) {
            $errors = [];

            foreach ($page->structured_data as $index => $block) {
                if (! ($block['valid'] ?? false)) {
                    $errors[] = 'blok ' . ($index + 1) . ': ' . ($block['error'] ?? 'ongeldig');
                }
            }

            if ($errors !== []) {
                yield $this->finding($page, ['count' => count($errors), 'errors' => implode('; ', $errors)]);
            }
        }
    }
}
