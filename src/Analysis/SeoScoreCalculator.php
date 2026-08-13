<?php

// packages/dashed/dashed-seo/src/Analysis/SeoScoreCalculator.php

declare(strict_types=1);

namespace Dashed\DashedSeo\Analysis;

use Dashed\DashedSeo\SeoCheckRegistry;

class SeoScoreCalculator
{
    /**
     * Elke controle heeft een gewicht op basis van ernst. Per controle telt het
     * aandeel getroffen pagina's, dus 80 dubbele titels op 350 pagina's is
     * 0,23. De strafpunten zijn de som van gewicht maal aandeel, en de score is
     * honderd min die som gedeeld door het totale gewicht.
     *
     * $weights en $checkSeverities zijn optioneel: worden ze niet meegegeven,
     * dan valt dit terug op de huidige config en de levende registry, precies
     * het oude gedrag. AnalyseSiteAuditJob geeft ze wel mee, met een
     * momentopname die het op het moment van de scan ook al in settings
     * vastlegt: zonder die momentopname telt dit altijd over de op dit moment
     * geregistreerde controles, en zou een controle die pas na deze audit is
     * toegevoegd het gewicht-totaal van een oud, nooit opnieuw geanalyseerd
     * rapport alsnog optisch laten verdunnen.
     *
     * @param array<string, int> $countsPerCheck
     * @param array<string, float|int>|null $weights
     * @param array<string, string>|null $checkSeverities check_key => severity
     */
    public static function calculate(array $countsPerCheck, int $pagesCrawled, ?array $weights = null, ?array $checkSeverities = null): int
    {
        if ($pagesCrawled <= 0) {
            return 100;
        }

        $weights ??= config('dashed-seo.weights');

        if ($checkSeverities === null) {
            $checkSeverities = [];
            foreach (app(SeoCheckRegistry::class)->checks() as $check) {
                $checkSeverities[$check->key()] = $check->severity();
            }
        }

        $total = 0.0;
        $penalty = 0.0;

        foreach ($checkSeverities as $key => $severity) {
            $weight = (float) ($weights[$severity] ?? 1);
            $total += $weight;

            $count = (int) ($countsPerCheck[$key] ?? 0);
            if ($count <= 0) {
                continue;
            }

            $share = min(1.0, $count / $pagesCrawled);
            $penalty += $weight * $share;
        }

        if ($total <= 0.0) {
            return 100;
        }

        $score = 100 * max(0.0, 1 - ($penalty / $total));

        return (int) round(min(100, $score));
    }
}
