<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Crawler;

class ParsedPage
{
    public function __construct(
        public ?string $title = null,
        public ?string $metaDescription = null,
        public ?string $canonical = null,
        public ?string $robotsMeta = null,
        public ?string $htmlLang = null,
        public array $h1s = [],
        public array $headingCounts = [],
        public int $wordCount = 0,
        public array $hreflangs = [],
        public array $images = [],
        public array $stylesheets = [],
        public array $scripts = [],
        public array $icons = [],
        public array $links = [],
        /** @var array<int, array{valid: bool, error: ?string, nodes: array}> per JSON-LD-blok, zie StructuredData::summarize() */
        public array $structuredData = [],
        /** Een <span wire:click="gotoPage(...)"> in plaats van een anker: de oude themapaginering. */
        public bool $nonCrawlablePagination = false,
    ) {
    }
}
