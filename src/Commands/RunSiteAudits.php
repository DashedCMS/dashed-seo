<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Commands;

use Illuminate\Console\Command;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedSeo\Audits\AuditGuard;
use Dashed\DashedSeo\Audits\AuditSweeper;
use Dashed\DashedSeo\Jobs\StartSiteAuditJob;
use Dashed\DashedSeo\Settings\AuditSettings;

class RunSiteAudits extends Command
{
    protected $signature = 'dashed:run-site-audits {--sweep-only : Alleen vastgelopen scans opruimen}';

    protected $description = 'Vastgelopen scans opruimen en de wekelijkse sitescans starten';

    public function handle(): int
    {
        // Vegen gaat altijd eerst, en over alle sites, ook die zonder
        // wekelijkse scan: anders blijft een handmatig gestarte scan die is
        // blijven hangen voorgoed staan op zo'n site.
        $swept = AuditSweeper::sweep();

        if ($swept > 0) {
            $this->info(__(':aantal vastgelopen scans opgeruimd.', ['aantal' => $swept]));
        }

        if ($this->option('sweep-only')) {
            return self::SUCCESS;
        }

        foreach (Sites::getSites() as $site) {
            if (! AuditSettings::forSite($site['id'])->weekly) {
                continue;
            }

            $audit = AuditGuard::claim($site['id'], 'scheduled');

            if (! $audit) {
                $this->line('<fg=yellow>' . __('Site :site overgeslagen: :reden', [
                    'site' => $site['name'],
                    'reden' => AuditGuard::problem($site['id']) ?? __('onbekend'),
                ]) . '</>');

                continue;
            }

            StartSiteAuditJob::dispatch($audit->id);

            $this->info(__('Scan gestart voor :site.', ['site' => $site['name']]));
        }

        return self::SUCCESS;
    }
}
