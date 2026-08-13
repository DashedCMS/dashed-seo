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
     * @param array<string, int> $countsPerCheck
     */
    public static function calculate(array $countsPerCheck, int $pagesCrawled): int
    {
        if ($pagesCrawled <= 0) {
            return 100;
        }

        $weights = config('dashed-seo.weights');
        $checks = app(SeoCheckRegistry::class)->checks();

        $total = 0.0;
        $penalty = 0.0;

        foreach ($checks as $check) {
            $weight = (float) ($weights[$check->severity()] ?? 1);
            $total += $weight;

            $count = (int) ($countsPerCheck[$check->key()] ?? 0);
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
