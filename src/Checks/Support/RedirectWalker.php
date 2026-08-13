<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\Support;

use Dashed\DashedSeo\Models\SiteAudit;
use Dashed\DashedSeo\Models\SiteAuditPage;
use Dashed\DashedSeo\Crawler\UrlNormalizer;

class RedirectWalker
{
    /** @return array{chain: array<int, string>, hops: int, loop: bool} */
    public static function walk(SiteAudit $audit, SiteAuditPage $page): array
    {
        $chain = [];
        $seen = [$page->url_hash => true];
        $current = $page;
        $loop = false;

        while ($current->redirect_target !== null) {
            $target = $current->redirect_target;
            $chain[] = $target;
            $hash = UrlNormalizer::hash($target);

            if (isset($seen[$hash])) {
                $loop = true;

                break;
            }

            $seen[$hash] = true;

            $next = SiteAuditPage::where('audit_id', $audit->id)->where('url_hash', $hash)->first();
            if ($next === null) {
                break;
            }

            $current = $next;
        }

        return ['chain' => $chain, 'hops' => count($chain), 'loop' => $loop];
    }
}
