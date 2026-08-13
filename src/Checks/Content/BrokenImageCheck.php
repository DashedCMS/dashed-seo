<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\Content;

use Dashed\DashedSeo\Models\SiteAudit;
use Dashed\DashedSeo\Checks\BaseCheck;
use Dashed\DashedSeo\Models\SiteAuditAsset;
use Dashed\DashedSeo\Crawler\UrlNormalizer;

class BrokenImageCheck extends BaseCheck
{
    public function key(): string
    {
        return 'broken_image';
    }

    public function run(SiteAudit $audit): iterable
    {
        $broken = SiteAuditAsset::where('audit_id', $audit->id)
            ->where('type', SiteAuditAsset::TYPE_IMAGE)
            ->where(fn ($query) => $query->whereNull('status_code')->orWhere('status_code', '>=', 400))
            ->pluck('url_hash')
            ->flip();

        if ($broken->isEmpty()) {
            return;
        }

        foreach ($this->indexablePages($audit)->orderBy('id')->cursor() as $page) {
            foreach ($page->images ?? [] as $image) {
                if ($broken->has(UrlNormalizer::hash($image['src']))) {
                    yield $this->finding($page, ['image' => $image['src']]);
                }
            }
        }
    }
}
