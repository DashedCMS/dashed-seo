<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks;

class Finding
{
    public function __construct(
        public string $checkKey,
        public string $section,
        public string $severity,
        public ?int $pageId = null,
        public ?string $url = null,
        public array $context = [],
    ) {
    }
}
