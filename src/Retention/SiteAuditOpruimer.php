<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Retention;

use Dashed\DashedCore\Retention\Termijn;
use Dashed\DashedSeo\Audits\AuditPruner;
use Dashed\DashedCore\Retention\Contracts\Opruimer;

/**
 * Sitescans worden niet verwijderd maar uitgekleed tot hun samenvatting: de
 * cijfers blijven, de duizenden losse regels met links en bevindingen gaan
 * weg. Daarom een eigen opruimer in plaats van een datumverwijdering.
 *
 * De eenheid van deze termijn is scans, geen dagen, dus wordt hier
 * `Termijn::waarde()` gebruikt en niet `dagen()`: dat aantal is precies wat
 * de beheerder op het instellingenscherm heeft ingevuld, en dat aantal geeft
 * deze opruimer door aan AuditPruner in plaats van de config nogmaals zelf
 * te lezen. Zonder die doorgifte zou het veld op het scherm decoratie zijn.
 */
class SiteAuditOpruimer implements Opruimer
{
    public function ruimOp(Termijn $termijn, int $portie, bool $droog): int
    {
        $keep = $termijn->waarde();

        if ($droog) {
            return AuditPruner::telUitTeKleden($keep);
        }

        return AuditPruner::prune($keep);
    }
}
