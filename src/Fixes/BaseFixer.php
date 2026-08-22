<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Fixes;

use Dashed\DashedSeo\Models\SiteAudit;
use Illuminate\Database\Eloquent\Model;
use Dashed\DashedSeo\Contracts\SeoFixer;
use Dashed\DashedSeo\Models\SiteAuditFix;
use Dashed\DashedSeo\Crawler\PathLocale;
use Dashed\DashedSeo\Models\SiteAuditFinding;

/**
 * De standaarden van een fixer. Omdat propose() de volledige oude en nieuwe
 * waarden vastlegt, is toepassen voor de meeste fixers niets anders dan de
 * velden uit after wegschrijven, en terugdraaien die uit before. Een fixer die
 * iets anders doet dan een veld schrijven overschrijft die twee.
 */
abstract class BaseFixer implements SeoFixer
{
    public function scope(): string
    {
        return self::SCOPE_FINDING;
    }

    public function defaultMode(): string
    {
        return SiteAuditFix::MODE_SUGGESTION;
    }

    /** Zie SeoFixer::aiCallsMade(). */
    protected int $aiCalls = 0;

    public function usesAi(): bool
    {
        return false;
    }

    public function aiCallsMade(): int
    {
        return $this->aiCalls;
    }

    public function resetAiCalls(): void
    {
        $this->aiCalls = 0;
    }

    /**
     * Vlak voor elke AI-aanroep, niet erna en niet bij het voorstel. Zo telt
     * ook een aanroep mee die op een tijdslimiet klapt of niets bruikbaars
     * teruggeeft.
     */
    protected function countAiCall(): void
    {
        $this->aiCalls++;
    }

    public function supportsRevert(): bool
    {
        return true;
    }

    public function isSiteWide(): bool
    {
        return false;
    }

    public function createsModel(): ?string
    {
        return null;
    }

    public function apply(SiteAuditFix $fix): void
    {
        $this->write($fix, $fix->after ?? []);
    }

    public function revert(SiteAuditFix $fix): void
    {
        $this->write($fix, $fix->before ?? []);
    }

    /**
     * De opgegeven velden op het subject zetten. Vertaalbare velden gaan via
     * setTranslation met de taal van de fix, want de job draait zonder request
     * en de app-locale zegt hier niets.
     */
    protected function write(SiteAuditFix $fix, array $values): void
    {
        $subject = $fix->subject;

        if (! $subject || $values === []) {
            return;
        }

        foreach ($values as $field => $value) {
            if ($fix->locale && method_exists($subject, 'getTranslation') && in_array($field, $subject->translatable ?? [], true)) {
                $subject->setTranslation($field, $fix->locale, $value);

                continue;
            }

            $subject->{$field} = $value;
        }

        $subject->save();
    }

    /** Het CMS-model achter de gecrawlde pagina, als dat er is. */
    protected function pageModel(SiteAuditFinding $finding): ?Model
    {
        $page = $finding->page;

        if (! $page || ! $page->model_type || ! $page->model_id || ! class_exists($page->model_type)) {
            return null;
        }

        return $page->model_type::find($page->model_id);
    }

    /**
     * De site waar deze bevinding bij hoort. Via de scan en niet via een
     * relatie op de bevinding: SiteAuditFinding kent alleen page(), en er is
     * geen reden om daar een relatie bij te zetten die verder nergens
     * gebruikt wordt.
     */
    protected function siteId(SiteAuditFinding $finding): ?string
    {
        return SiteAudit::find($finding->audit_id)?->site_id;
    }

    /**
     * De taal waarin deze bevinding opgelost hoort te worden, of null wanneer
     * die niet vast te stellen is.
     *
     * De taal staat op de gecrawlde pagina, afgeleid uit het pad. Ontbreekt hij
     * daar (een scan van voor die afleiding bestond, of een pagina die buiten
     * de crawler om is aangemaakt), dan is er op een site met meer dan een taal
     * niets te raden: een fixer zou dan in de taal van de queue-worker
     * schrijven, de verkeerde inhoud lezen en een met de hand geschreven tekst
     * in een andere taal overschrijven. Heeft de site maar een taal, dan valt
     * er niets te verwarren en is die ene taal het antwoord.
     */
    protected function localeFor(SiteAuditFinding $finding): ?string
    {
        $locale = $finding->page?->locale;

        if (is_string($locale) && $locale !== '') {
            return $locale;
        }

        $talen = PathLocale::localesFor((string) $this->siteId($finding));

        return count($talen) === 1 ? $talen[0] : null;
    }

    /** Het pad van een adres, met een schuine streep ervoor en zonder eentje erachter. */
    protected function path(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '/';

        if ($path === '/') {
            return '/';
        }

        return '/' . trim($path, '/');
    }
}
