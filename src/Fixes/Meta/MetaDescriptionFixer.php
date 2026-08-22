<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Fixes\Meta;

use Dashed\DashedAi\Facades\Ai;
use Dashed\DashedSeo\Fixes\BaseFixer;
use Dashed\DashedSeo\Fixes\FixProposal;
use Dashed\DashedSeo\Models\SiteAuditFinding;

/**
 * De meta description laten schrijven op basis van de pagina zelf. Schrijft
 * naar Metadata.description, per taal.
 */
class MetaDescriptionFixer extends BaseFixer
{
    /**
     * Metadata kapt bij elke opslag af op 170 tekens zonder beletselteken (zie
     * Metadata::booted() in dashed-core). Zelfde reden als de grens in
     * MetaTitleFixer.
     */
    protected const MAX_BREEDTE = 170;

    public function checkKeys(): array
    {
        return ['description_missing', 'description_too_long', 'duplicate_description'];
    }

    public function usesAi(): bool
    {
        return true;
    }

    public function label(): string
    {
        return __('Meta description laten schrijven');
    }

    public function propose(SiteAuditFinding $finding): ?FixProposal
    {
        $model = $this->pageModel($finding);

        if (! $model || ! method_exists($model, 'metadata')) {
            return null;
        }

        $locale = $this->localeFor($finding);

        // Zonder taal is er niets te schrijven: zie BaseFixer::localeFor().
        if ($locale === null) {
            return null;
        }

        $context = PageContext::for($model, $locale);

        if ($context === '') {
            return null;
        }

        // Geen em-dashes, geen AI-cliches: die huisstijlregels zet
        // AiProvider::buildSystemPrompt() zelf voor elke aanroep, tenzij
        // disable_brand_rules wordt meegegeven. Niet hier herhalen, anders
        // lopen de twee teksten op den duur uit elkaar.
        $this->countAiCall();

        $antwoord = Ai::json($this->prompt($context), ['system' => $this->system()]);
        $omschrijving = trim((string) ($antwoord['description'] ?? ''));

        // Zelfde afweging als bij de titel: te lang is onbruikbaar, niet iets
        // om stilzwijgend af te kappen.
        if ($omschrijving === '' || mb_strwidth($omschrijving, 'UTF-8') > self::MAX_BREEDTE) {
            return null;
        }

        $metadata = $model->metadata()->firstOrCreate([]);
        $oud = (string) $metadata->getTranslation('description', $locale);

        if ($oud === $omschrijving) {
            return null;
        }

        return new FixProposal(
            subject: $metadata,
            locale: $locale,
            before: ['description' => $oud],
            after: ['description' => $omschrijving],
            summary: __('Meta description: ":tekst"', ['tekst' => $omschrijving]),
        );
    }

    protected function system(): string
    {
        return 'Je schrijft meta descriptions voor een Nederlandse website. Antwoord uitsluitend met JSON in de vorm {"description": "..."}.';
    }

    protected function prompt(string $context): string
    {
        return "Schrijf een meta description van maximaal 155 tekens voor deze pagina. "
            . "Vertel wat de bezoeker op de pagina vindt, in de je-vorm, zonder aanhalingstekens.\n\n"
            . "Pagina:\n" . $context;
    }
}
