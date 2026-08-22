<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Fixes\Links;

use Dashed\DashedSeo\Fixes\BaseFixer;
use Dashed\DashedSeo\Fixes\FixProposal;
use Dashed\DashedSeo\Models\SiteAuditPage;
use Dashed\DashedSeo\Models\SiteAuditFinding;
use Dashed\DashedCore\Classes\ContentLinkRewriter;

/**
 * Een interne link die naar een doorverwijzing wijst laten wijzen naar het
 * adres waar die doorverwijzing op uitkomt.
 *
 * Standaard een voorstel en geen automatische fix: dit schrijft in
 * gepubliceerde inhoud, en dat hoort iemand gezien te hebben.
 */
class RelinkToFinalUrlFixer extends BaseFixer
{
    public function checkKeys(): array
    {
        return ['internal_link_to_redirect'];
    }

    public function label(): string
    {
        return __('Link naar het einddoel laten wijzen');
    }

    public function propose(SiteAuditFinding $finding): ?FixProposal
    {
        $doel = $finding->context['target'] ?? null;
        $model = $this->pageModel($finding);

        if (! is_string($doel) || ! $model) {
            return null;
        }

        $eind = $this->finalUrlFor($finding, $doel);

        if ($eind === null || $this->path($eind) === $this->path($doel)) {
            return null;
        }

        $locale = $this->localeFor($finding);

        // Zonder taal is er niets te schrijven: zie BaseFixer::localeFor().
        if ($locale === null) {
            return null;
        }

        $resultaat = (new ContentLinkRewriter($model, $locale))
            ->rewriteHref($this->path($doel), $this->path($eind));

        if (! $resultaat['changed']) {
            return null;
        }

        return new FixProposal(
            subject: $model,
            locale: $locale,
            before: $resultaat['before'],
            after: $resultaat['after'],
            summary: __('De link naar :oud vervangen door :nieuw', [
                'oud' => $this->path($doel),
                'nieuw' => $this->path($eind),
            ]),
        );
    }

    /**
     * Het einde van de doorverwijzingsketen, uit de gecrawlde pagina van het
     * aangelinkte adres. Zonder gecrawlde pagina weten we het niet, en dan
     * gokken we niet.
     */
    protected function finalUrlFor(SiteAuditFinding $finding, string $doel): ?string
    {
        $pagina = SiteAuditPage::where('audit_id', $finding->audit_id)
            ->where('url_hash', hash('sha256', $doel))
            ->first();

        if (! $pagina) {
            return null;
        }

        $keten = $pagina->redirect_chain ?? [];

        if (is_array($keten) && $keten !== []) {
            return (string) end($keten);
        }

        return $pagina->redirect_target ? (string) $pagina->redirect_target : null;
    }
}
