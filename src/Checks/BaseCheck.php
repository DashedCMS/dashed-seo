<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks;

use Dashed\DashedSeo\Models\SiteAudit;
use Dashed\DashedSeo\Contracts\SeoCheck;
use Dashed\DashedSeo\Models\SiteAuditPage;
use Illuminate\Database\Eloquent\Builder;

abstract class BaseCheck implements SeoCheck
{
    public function section(): string
    {
        return $this->definition()['section'];
    }

    public function severity(): string
    {
        return $this->definition()['severity'];
    }

    public function label(): string
    {
        return __($this->definition()['label']);
    }

    public function description(): string
    {
        return __($this->definition()['description']);
    }

    public function howToFix(): string
    {
        return __($this->definition()['how_to_fix']);
    }

    /** Alle pagina's die daadwerkelijk zijn opgehaald. */
    protected function pages(SiteAudit $audit): Builder
    {
        return $audit->pages()->getQuery()->where('crawl_status', SiteAuditPage::CRAWL_DONE);
    }

    /** Alleen de pagina's die een gewone, indexeerbare 200 teruggaven. */
    protected function indexablePages(SiteAudit $audit): Builder
    {
        return $this->pages($audit)->where('status_code', 200)->where('indexable', true);
    }

    protected function finding(SiteAuditPage $page, array $context = []): Finding
    {
        return new Finding($this->key(), $this->section(), $this->severity(), $page->id, $page->url, $context);
    }

    protected function siteFinding(array $context = []): Finding
    {
        return new Finding($this->key(), $this->section(), $this->severity(), null, null, $context);
    }

    protected function threshold(string $name): int
    {
        return (int) config("dashed-seo.thresholds.{$name}");
    }

    protected function definition(): array
    {
        return config("dashed-seo-checks.{$this->key()}");
    }
}
