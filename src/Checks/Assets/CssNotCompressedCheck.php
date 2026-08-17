<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\Assets;

use Dashed\DashedSeo\Checks\BaseCheck;
use Dashed\DashedSeo\Models\SiteAudit;
use Dashed\DashedSeo\Crawler\UrlNormalizer;
use Dashed\DashedSeo\Models\SiteAuditAsset;

class CssNotCompressedCheck extends BaseCheck
{
    public function key(): string
    {
        return 'css_not_compressed';
    }

    public function run(SiteAudit $audit): iterable
    {
        $uncompressed = SiteAuditAsset::where('audit_id', $audit->id)
            ->where('type', SiteAuditAsset::TYPE_CSS)
            ->where('compressed', false)
            ->pluck('url', 'url_hash');

        if ($uncompressed->isEmpty()) {
            return;
        }

        foreach ($this->pages($audit)->where('status_code', 200)->orderBy('id')->cursor() as $page) {
            $hits = [];

            foreach ($page->stylesheets ?? [] as $url) {
                if ($uncompressed->has(UrlNormalizer::hash($url))) {
                    $hits[] = $url;
                }
            }

            if ($hits !== []) {
                yield $this->finding($page, ['resources' => $hits]);
            }
        }
    }
}
