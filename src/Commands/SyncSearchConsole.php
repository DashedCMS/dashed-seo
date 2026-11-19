<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Commands;

use Illuminate\Console\Command;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedSeo\Jobs\SyncSearchConsoleJob;
use Dashed\DashedSeo\Settings\SearchConsoleSettings;

class SyncSearchConsole extends Command
{
    protected $signature = 'dashed:sync-search-console {--site= : alleen deze site} {--now : niet via de wachtrij}';

    protected $description = 'Haalt per pagina en zoekterm de cijfers uit Google Search Console op';

    public function handle(): int
    {
        foreach (Sites::getSites() as $site) {
            $siteId = (string) $site['id'];

            if (($this->option('site') && $this->option('site') !== $siteId) || ! SearchConsoleSettings::ready($siteId)) {
                continue;
            }

            $this->option('now') ? SyncSearchConsoleJob::dispatchSync($siteId) : SyncSearchConsoleJob::dispatch($siteId);
            $this->info("Search Console: {$siteId}");
        }

        return self::SUCCESS;
    }
}
