<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\Assets;

use Dashed\DashedSeo\Checks\BaseCheck;
use Dashed\DashedSeo\Models\SiteAudit;
use Dashed\DashedSeo\Crawler\UrlNormalizer;
use Dashed\DashedSeo\Models\SiteAuditAsset;

class CssTooLargeCheck extends BaseCheck
{
    public function key(): string
    {
        return 'css_too_large';
    }

    public function run(SiteAudit $audit): iterable
    {
        $tooLarge = SiteAuditAsset::where('audit_id', $audit->id)
            ->where('type', SiteAuditAsset::TYPE_CSS)
            ->where('size', '>', $this->threshold('css_max_bytes'))
            ->pluck('url', 'url_hash');

        if ($tooLarge->isEmpty()) {
            return;
        }

        foreach ($this->pages($audit)->where('status_code', 200)->orderBy('id')->cursor() as $page) {
            $hits = [];

            foreach ($page->stylesheets ?? [] as $url) {
                if ($tooLarge->has(UrlNormalizer::hash($url))) {
                    $hits[] = $url;
                }
            }

            if ($hits !== []) {
                yield $this->finding($page, ['resources' => $hits]);
            }
        }
    }
}
