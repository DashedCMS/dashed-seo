<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Fixes\Links;

use Dashed\DashedSeo\Fixes\BaseFixer;
use Dashed\DashedSeo\Fixes\FixProposal;
use Dashed\DashedSeo\Models\SiteAuditFinding;
use Dashed\DashedCore\Classes\ContentLinkRewriter;

/**
 * Een interne link naar een adres dat een 4XX geeft, laten wijzen naar waar
 * die pagina volgens de url-historie naartoe verhuisd is. Staat er niets in
 * de historie, dan stellen we niets voor: een link naar een willekeurige
 * andere pagina is erger dan een kapotte link, en gelijkenis tussen adressen
 * is daarvoor het verkeerde instrument (/product-a en /product-b lijken voor
 * negentig procent op elkaar en zijn twee verschillende producten).
 */
class RelinkBrokenInternalFixer extends BaseFixer
{
    public function checkKeys(): array
    {
        return ['internal_link_to_4xx'];
    }

    public function label(): string
    {
        return __('Kapotte interne link naar de verhuisde pagina laten wijzen');
    }

    public function propose(SiteAuditFinding $finding): ?FixProposal
    {
        $doel = $finding->context['target'] ?? null;
        $model = $this->pageModel($finding);

        if (! is_string($doel) || ! $model) {
            return null;
        }

        $siteId = $this->siteId($finding);

        if (! $siteId) {
            return null;
        }

        $locale = $this->localeFor($finding);

        // Zonder taal is er niets te schrijven: zie BaseFixer::localeFor().
        if ($locale === null) {
            return null;
        }

        $vervanger = MovedUrlResolver::currentUrlFor($this->path($doel), $siteId, $locale);

        if ($vervanger === null || $this->path($vervanger) === $this->path($doel)) {
            return null;
        }

        $resultaat = (new ContentLinkRewriter($model, $locale))
            ->rewriteHref($this->path($doel), $this->path($vervanger));

        if (! $resultaat['changed']) {
            return null;
        }

        return new FixProposal(
            subject: $model,
            locale: $locale,
            before: $resultaat['before'],
            after: $resultaat['after'],
            summary: __('De kapotte link naar :oud vervangen door :nieuw', [
                'oud' => $this->path($doel),
                'nieuw' => $this->path($vervanger),
            ]),
        );
    }
}
