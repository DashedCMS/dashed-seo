<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Fixes\Meta;

use Dashed\DashedAi\Facades\Ai;
use Dashed\DashedSeo\Fixes\BaseFixer;
use Dashed\DashedSeo\Fixes\FixProposal;
use Dashed\DashedSeo\Models\SiteAuditFinding;

/**
 * De meta title laten schrijven op basis van de pagina zelf. Schrijft naar
 * Metadata.title, per taal.
 */
class MetaTitleFixer extends BaseFixer
{
    /**
     * Metadata kapt bij elke opslag af op 70 tekens zonder beletselteken (zie
     * Metadata::booted() in dashed-core). Een langer antwoord komt dus als
     * halve zin op de site terecht terwijl het goedkeurscherm de volledige
     * tekst laat zien, en dat is precies de afgekapte titel die dit hoort te
     * voorkomen.
     */
    protected const MAX_BREEDTE = 70;

    public function checkKeys(): array
    {
        return ['title_missing', 'title_too_short', 'title_too_long', 'duplicate_title', 'title_equals_h1'];
    }

    public function usesAi(): bool
    {
        return true;
    }

    public function label(): string
    {
        return __('Meta title laten schrijven');
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
        $titel = trim((string) ($antwoord['title'] ?? ''));

        // Te lang telt hier als onbruikbaar en niet als iets om af te kappen:
        // wat voorgesteld en goedgekeurd wordt moet dezelfde tekst zijn als
        // wat er op de site komt te staan. Bij de volgende scan komt de
        // bevinding gewoon terug.
        if ($titel === '' || mb_strwidth($titel, 'UTF-8') > self::MAX_BREEDTE) {
            return null;
        }

        $metadata = $model->metadata()->firstOrCreate([]);
        $oud = (string) $metadata->getTranslation('title', $locale);

        if ($oud === $titel) {
            return null;
        }

        return new FixProposal(
            subject: $metadata,
            locale: $locale,
            before: ['title' => $oud],
            after: ['title' => $titel],
            summary: __('Meta title: ":titel"', ['titel' => $titel]),
        );
    }

    protected function system(): string
    {
        return 'Je schrijft meta titles voor een Nederlandse website. Antwoord uitsluitend met JSON in de vorm {"title": "..."}.';
    }

    protected function prompt(string $context): string
    {
        // De grens van 60 tekens en niet 70: Metadata kapt bij opslaan af op
        // 70, en dan wil je marge in plaats van een titel die er net langs
        // schuurt.
        return "Schrijf een meta title van maximaal 60 tekens voor deze pagina. "
            . "Noem waar de pagina over gaat, gebruik geen aanhalingstekens en geen merknaam aan het eind.\n\n"
            . "Pagina:\n" . $context;
    }
}
