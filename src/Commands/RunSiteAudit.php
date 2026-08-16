<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Commands;

use Illuminate\Console\Command;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedSeo\Audits\AuditGuard;
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
        $requestedSiteId = $this->argument('site');
        $siteId = $requestedSiteId ?: Sites::getActive();

        // De basis-URL komt nu uit de instellingen van de opgegeven site, niet
        // meer uit de applicatieconfiguratie. Daarmee vervalt ook de
        // waarschuwing die hier stond over een site-argument dat niets deed.
        $audit = AuditGuard::claim($siteId, 'manual');

        if (! $audit) {
            $this->error(AuditGuard::problem($siteId) ?? __('De scan kon niet gestart worden.'));

            return self::FAILURE;
        }

        $baseUrl = $audit->base_url;

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

        if ($audit->fresh()->status === SiteAudit::STATUS_CRAWLING) {
            // De bewakingsteller is geraakt terwijl de crawl nog niet klaar
            // was: er is dan geen enkele controle over deze scan gedraaid.
            // Zonder deze afhandeling viel dit stilzwijgend door naar de
            // score hieronder, en meldde het commando succes voor een scan
            // die nooit is geanalyseerd.
            $audit->update([
                'status' => SiteAudit::STATUS_FAILED,
                'failure_reason' => __('De crawl is niet binnen :rondes rondes afgerond, de scan is afgebroken.', [
                    'rondes' => $guard,
                ]),
                'finished_at' => now(),
            ]);
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
