<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\Support;

use Dashed\DashedSeo\Models\SiteAudit;
use Dashed\DashedSeo\Models\SiteAuditPage;
use Dashed\DashedSeo\Crawler\UrlNormalizer;

class HreflangGraph
{
    /** @var array<int, array<int, array{lang: string, target: string, target_hash: string}>> */
    protected array $entries = [];

    /** @var array<string, SiteAuditPage> */
    protected array $pages = [];

    /** @var array<string, array<int, string>> */
    protected array $langsPerTarget = [];

    public static function build(SiteAudit $audit): self
    {
        $graph = new self();

        foreach ($audit->pages()->getQuery()->where('crawl_status', SiteAuditPage::CRAWL_DONE)->orderBy('id')->cursor() as $page) {
            $graph->pages[$page->url_hash] = $page;

            foreach ($page->hreflangs ?? [] as $entry) {
                $target = UrlNormalizer::normalize($entry['url'] ?? '', $audit->base_url);
                if ($target === null) {
                    continue;
                }

                $hash = UrlNormalizer::hash($target);
                $lang = (string) ($entry['lang'] ?? '');

                $graph->entries[$page->id][] = ['lang' => $lang, 'target' => $target, 'target_hash' => $hash];

                if (! in_array($lang, $graph->langsPerTarget[$hash] ?? [], true)) {
                    $graph->langsPerTarget[$hash][] = $lang;
                }
            }
        }

        return $graph;
    }

    /** @return array<int, array{lang: string, target: string, target_hash: string}> */
    public function entriesFor(int $pageId): array
    {
        return $this->entries[$pageId] ?? [];
    }

    public function pageByHash(string $hash): ?SiteAuditPage
    {
        return $this->pages[$hash] ?? null;
    }

    public function declaresTarget(int $pageId, string $targetHash): bool
    {
        foreach ($this->entriesFor($pageId) as $entry) {
            if ($entry['target_hash'] === $targetHash) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, array<int, string>> */
    public function langsPerTarget(): array
    {
        return $this->langsPerTarget;
    }

    /** @return array<int, SiteAuditPage> */
    public function pagesWithHreflang(): array
    {
        return array_values(array_filter(
            $this->pages,
            fn (SiteAuditPage $page) => ($this->entries[$page->id] ?? []) !== [],
        ));
    }
}
