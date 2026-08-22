<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Contracts;

use Dashed\DashedSeo\Fixes\FixProposal;
use Dashed\DashedSeo\Models\SiteAuditFix;
use Dashed\DashedSeo\Models\SiteAuditFinding;

interface SeoFixer
{
    public const SCOPE_FINDING = 'finding';
    public const SCOPE_SITE = 'site';

    /** @return array<int, string> Aan welke controles deze fixer hangt. */
    public function checkKeys(): array;

    /** SCOPE_FINDING of SCOPE_SITE. Bij SCOPE_SITE dekt één voorstel alle bevindingen van die controle af. */
    public function scope(): string;

    /** SiteAuditFix::MODE_AUTO of MODE_SUGGESTION. */
    public function defaultMode(): string;

    public function label(): string;

    /** Roept deze fixer de AI aan, en telt hij daarmee mee tegen het plafond per scan? */
    public function usesAi(): bool;

    /**
     * Hoeveel AI-aanroepen er gedaan zijn sinds de laatste resetAiCalls().
     *
     * Het plafond telt aanroepen en geen voorstellen. Een fixer die de AI
     * aanroept en daarna null teruggeeft heeft die aanroep wel degelijk
     * gedaan, en zonder dit is elk zo'n pad gratis tegen het plafond terwijl
     * het geld kost.
     */
    public function aiCallsMade(): int;

    /** Zet de teller op nul, voor de volgende bevinding. */
    public function resetAiCalls(): void;

    public function supportsRevert(): bool;

    /**
     * Raakt deze fixer de hele site in plaats van een enkel record?
     *
     * De sitemap opnieuw bouwen en een regel in robots.txt zetten gelden voor
     * alles wat er staat, en daar is alleen een superbeheerder voor. Bewust
     * een uitspraak van de fixer zelf en niet afgeleid uit een leeg subject:
     * MissingPageRedirectFixer heeft bij het voorstellen ook nog geen subject
     * en maakt toch maar een enkele redirectrij aan.
     */
    public function isSiteWide(): bool;

    /**
     * De klasse waarvan deze fixer bij toepassen een nieuw record aanmaakt, of
     * null wanneer hij alleen bestaande records wijzigt.
     *
     * Een fixer die een record aanmaakt heeft bij het voorstellen nog geen
     * subject, en dan is er geen rij om een policy op te vragen. Er is wel het
     * create-recht op de klasse zelf, en dat is precies de vraag die het
     * bewerkscherm van dat model ook stelt.
     */
    public function createsModel(): ?string;

    /** Null betekent: deze bevinding is niet op te lossen. Dat is geen fout. */
    public function propose(SiteAuditFinding $finding): ?FixProposal;

    public function apply(SiteAuditFix $fix): void;

    public function revert(SiteAuditFix $fix): void;
}
