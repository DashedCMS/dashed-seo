<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Commands;

use Illuminate\Console\Command;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedSeo\Models\SiteAudit;
use Dashed\DashedSeo\Jobs\StartSiteAuditJob;
use Dashed\DashedSeo\Jobs\AnalyseSiteAuditJob;
use Dashed\DashedSeo\Jobs\CrawlSiteAuditBatchJob;

class RunSiteAudit extends Command
{
    protected $signature = 'dashed:site-audit {site?} {--sync : De hele scan in de voorgrond draaien}';

    protected $description = 'Een SEO-audit starten voor een site';

    public function handle(): int
    {
        $siteId = $this->argument('site') ?: Sites::getActive();

        $audit = SiteAudit::create([
            'site_id' => $siteId,
            'base_url' => rtrim(config('app.url'), '/'),
            'status' => SiteAudit::STATUS_PENDING,
            'trigger' => 'manual',
        ]);

        if (! $this->option('sync')) {
            StartSiteAuditJob::dispatch($audit->id);
            $this->info(__('De scan staat in de wachtrij.'));

            return self::SUCCESS;
        }

        (new StartSiteAuditJob($audit->id))->handle();

        // De crawler zet zichzelf normaal opnieuw in de wachtrij. In de
        // voorgrond wordt er net zo lang een partij gedraaid tot de voorraad
        // leeg is en de scan naar de beoordelingsfase gaat. Met een echte
        // queue-driver (redis/database) staat de audit hier nog op crawling,
        // en drijft deze lus de partijen zelf aan. Met de sync-driver (zoals
        // in de testsuite) heeft StartSiteAuditJob::handle() de hele keten
        // via zijn eigen dispatch()-aanroepen al synchroon afgerond voordat
        // we hier komen, en doet de lus hieronder simpelweg niets: de status
        // staat dan al niet meer op crawling.
        $guard = 0;
        while ($audit->fresh()->status === SiteAudit::STATUS_CRAWLING && $guard < 1000) {
            (new CrawlSiteAuditBatchJob($audit->id))->handle();
            $guard++;
        }

        if ($audit->fresh()->status === SiteAudit::STATUS_ANALYSING) {
            (new AnalyseSiteAuditJob($audit->id))->handle();
        }

        $audit->refresh();

        if ($audit->status === SiteAudit::STATUS_FAILED) {
            $this->error($audit->failure_reason ?? __('De scan is mislukt.'));

            return self::FAILURE;
        }

        $this->info(__('Score: :score, :pages pagina\'s, :errors fouten.', [
            'score' => $audit->health_score,
            'pages' => $audit->pages_crawled,
            'errors' => $audit->error_count,
        ]));

        return self::SUCCESS;
    }
}
