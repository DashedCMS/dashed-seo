<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Fixes\Links;

use Dashed\DashedSeo\Fixes\BaseFixer;
use Dashed\DashedSeo\Fixes\FixProposal;
use Dashed\DashedSeo\Models\SiteAuditFinding;
use Dashed\DashedCore\Classes\ContentLinkRewriter;

/**
 * Nofollow op een interne link houdt zoekmachines tegen op je eigen site en
 * heeft daar zelden een reden voor.
 *
 * Deterministisch, maar toch standaard een voorstel: dit herschrijft HTML in
 * gepubliceerde inhoud, en dat is het criterium, niet of de uitkomst vaststaat.
 */
class RemoveInternalNofollowFixer extends BaseFixer
{
    public function checkKeys(): array
    {
        return ['internal_nofollow_link'];
    }

    public function label(): string
    {
        return __('Nofollow van een interne link halen');
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

        $resultaat = (new ContentLinkRewriter($model, $locale))
            ->removeRel($this->path($doel), 'nofollow');

        if (! $resultaat['changed']) {
            return null;
        }

        return new FixProposal(
            subject: $model,
            locale: $locale,
            before: $resultaat['before'],
            after: $resultaat['after'],
            summary: __('Nofollow weghalen van de link naar :doel', ['doel' => $this->path($doel)]),
        );
    }
}
