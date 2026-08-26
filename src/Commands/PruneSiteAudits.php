<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Commands;

use Illuminate\Console\Command;

/**
 * Verouderd: sitescans staan aangemeld in het bewaartermijnenregister en
 * worden nu uitgekleed door dashed:prune. Dit command blijft bestaan als
 * alias, zodat een bestaande cronregel op een productieserver niet in één
 * klap kapot gaat.
 */
class PruneSiteAudits extends Command
{
    protected $signature = 'dashed:prune-site-audits';

    protected $description = 'Verouderd. Gebruik dashed:prune. Oude sitescans uitkleden tot alleen hun samenvatting.';

    public function handle(): int
    {
        return $this->call('dashed:prune', [
            '--only' => 'site_audits',
        ]);
    }
}
