<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Fixes\Links;

use Dashed\DashedSeo\Fixes\BaseFixer;
use Dashed\DashedSeo\Fixes\FixProposal;
use Dashed\DashedSeo\Models\SiteAuditFinding;
use Dashed\DashedCore\Classes\ContentLinkRewriter;

/**
 * Een externe link die nergens meer op uitkomt. Waar hij naartoe had moeten
 * wijzen weten wij niet, dus het enige zinnige is de link weghalen en de tekst
 * laten staan.
 */
class UnlinkBrokenExternalFixer extends BaseFixer
{
    public function checkKeys(): array
    {
        return ['broken_external_link'];
    }

    public function label(): string
    {
        return __('Kapotte externe link ontkoppelen');
    }

    public function propose(SiteAuditFinding $finding): ?FixProposal
    {
        $doel = $finding->context['target'] ?? null;
        $model = $this->pageModel($finding);

        if (! is_string($doel) || ! $model) {
            return null;
        }

        $locale = $this->localeFor($finding);

        // Zonder taal is er niets te schrijven: zie BaseFixer::localeFor().
        if ($locale === null) {
            return null;
        }

        $resultaat = (new ContentLinkRewriter($model, $locale))->unlink($doel);

        if (! $resultaat['changed']) {
            return null;
        }

        return new FixProposal(
            subject: $model,
            locale: $locale,
            before: $resultaat['before'],
            after: $resultaat['after'],
            summary: __('De link naar :doel weghalen en de tekst laten staan', ['doel' => $doel]),
        );
    }
}
