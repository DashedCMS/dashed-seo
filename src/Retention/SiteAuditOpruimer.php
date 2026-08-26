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
 */
class SiteAuditOpruimer implements Opruimer
{
    public function ruimOp(Termijn $termijn, int $portie, bool $droog): int
    {
        if ($droog) {
            return AuditPruner::telUitTeKleden();
        }

        return AuditPruner::prune();
    }
}
