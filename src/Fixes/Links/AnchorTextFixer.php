<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Fixes\Links;

use Dashed\DashedAi\Facades\Ai;
use Dashed\DashedSeo\Fixes\BaseFixer;
use Dashed\DashedSeo\Fixes\FixProposal;
use Dashed\DashedSeo\Models\SiteAuditFinding;
use Dashed\DashedCore\Classes\ContentLinkRewriter;

/**
 * Een link zonder ankertekst zegt niets over waar hij heen gaat. De AI schrijft
 * er een op basis van het adres waar de link naartoe wijst.
 */
class AnchorTextFixer extends BaseFixer
{
    public function checkKeys(): array
    {
        return ['internal_link_without_anchor', 'external_link_without_anchor'];
    }

    public function usesAi(): bool
    {
        return true;
    }

    public function label(): string
    {
        return __('Ankertekst laten schrijven');
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

        $rewriter = new ContentLinkRewriter($model, $locale);
        $pad = $this->path($doel);

        // Eerst droog draaien, en de AI pas aanroepen als er werkelijk iets te
        // vullen valt. Een link om een afbeelding is ook een link zonder
        // ankertekst en blijft terecht ongemoeid; zonder deze volgorde kost
        // elke zo'n link een AI-aanroep die niets oplevert, en op een site met
        // honderden logo's, banners en producttegels is dat de hoofdmoot.
        // De proeftekst zelf wordt weggegooid en komt nergens terecht.
        if (! $rewriter->setAnchor($pad, 'x')['changed']) {
            return null;
        }

        $this->countAiCall();

        $antwoord = Ai::json(
            "Schrijf een korte ankertekst van hoogstens vijf woorden voor een link naar dit adres: {$doel}\n"
            . 'Beschrijf waar de link heen gaat. Niet "klik hier" of "lees meer".',
            ['system' => 'Antwoord uitsluitend met JSON in de vorm {"anchor": "..."}.'],
        );

        $anker = trim((string) ($antwoord['anchor'] ?? ''));

        if ($anker === '') {
            return null;
        }

        $resultaat = $rewriter->setAnchor($pad, $anker);

        if (! $resultaat['changed']) {
            return null;
        }

        return new FixProposal(
            subject: $model,
            locale: $locale,
            before: $resultaat['before'],
            after: $resultaat['after'],
            summary: __('Ankertekst ":tekst" voor de link naar :doel', ['tekst' => $anker, 'doel' => $doel]),
        );
    }
}
