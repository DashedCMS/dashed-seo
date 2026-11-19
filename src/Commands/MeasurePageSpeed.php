<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Commands;

use Illuminate\Console\Command;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedSeo\Jobs\MeasurePageSpeedJob;
use Dashed\DashedSeo\Settings\PageSpeedSettings;

class MeasurePageSpeed extends Command
{
    protected $signature = 'dashed:measure-pagespeed {--site= : alleen deze site} {--now : niet via de wachtrij}';

    protected $description = 'Meet de ingestelde URL\'s via PageSpeed Insights, mobiel en desktop';

    public function handle(): int
    {
        foreach (Sites::getSites() as $site) {
            $siteId = (string) $site['id'];

            if (($this->option('site') && $this->option('site') !== $siteId) || ! PageSpeedSettings::ready($siteId)) {
                continue;
            }

            $this->option('now') ? MeasurePageSpeedJob::dispatchSync($siteId) : MeasurePageSpeedJob::dispatch($siteId);
            $this->info("PageSpeed: {$siteId}");
        }

        return self::SUCCESS;
    }
}
