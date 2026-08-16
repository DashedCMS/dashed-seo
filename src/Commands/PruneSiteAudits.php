<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Commands;

use Illuminate\Console\Command;
use Dashed\DashedSeo\Audits\AuditPruner;

class PruneSiteAudits extends Command
{
    protected $signature = 'dashed:prune-site-audits';

    protected $description = 'Oude sitescans uitkleden tot alleen hun samenvatting';

    public function handle(): int
    {
        $pruned = AuditPruner::prune();

        $this->info(__(':aantal scans uitgekleed.', ['aantal' => $pruned]));

        return self::SUCCESS;
    }
}
