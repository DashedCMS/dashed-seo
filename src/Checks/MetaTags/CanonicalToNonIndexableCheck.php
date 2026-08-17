<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\MetaTags;

use Dashed\DashedSeo\Checks\BaseCheck;
use Dashed\DashedSeo\Models\SiteAudit;
use Dashed\DashedSeo\Models\SiteAuditPage;
use Dashed\DashedSeo\Crawler\UrlNormalizer;

class CanonicalToNonIndexableCheck extends BaseCheck
{
    public function key(): string
    {
        return 'canonical_to_non_indexable';
    }

    public function run(SiteAudit $audit): iterable
    {
        foreach ($this->indexablePages($audit)->whereNotNull('canonical')->orderBy('id')->cursor() as $page) {
            $canonical = UrlNormalizer::normalize($page->canonical, $audit->base_url);

            if ($canonical === null || UrlNormalizer::hash($canonical) === $page->url_hash) {
                continue;
            }

            $target = SiteAuditPage::where('audit_id', $audit->id)
                ->where('url_hash', UrlNormalizer::hash($canonical))
                ->first();

            if ($target !== null && ! ($target->status_code === 200 && $target->indexable)) {
                yield $this->finding($page, [
                    'canonical' => $canonical,
                    'target_status' => $target->status_code,
                ]);
            }
        }
    }
}
