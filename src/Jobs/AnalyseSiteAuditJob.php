<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Dashed\DashedSeo\Models\SiteAudit;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedSeo\SeoCheckRegistry;
use Illuminate\Queue\InteractsWithQueue;
use Dashed\DashedSeo\Models\SiteAuditFinding;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedSeo\Analysis\AuditComparer;
use Dashed\DashedSeo\Models\SiteAuditCheckResult;
use Dashed\DashedSeo\Checks\Support\LinkResolver;
use Dashed\DashedSeo\Analysis\SeoScoreCalculator;
use Dashed\DashedSeo\Checks\Support\ExternalLinkChecker;

class AnalyseSiteAuditJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $auditId)
    {
        $this->onQueue('seo');
    }

    public function handle(): void
    {
        $audit = SiteAudit::find($this->auditId);
        if (! $audit || $audit->status !== SiteAudit::STATUS_ANALYSING) {
            // Ook de weg terug: als deze job dubbel wordt aangeboden (een
            // herstart, een dubbele dispatch) staat de status na de eerste
            // uitvoering al op completed en stopt de tweede meteen, zonder
            // bevindingen dubbel weg te schrijven.
            return;
        }

        // Idempotentie in plaats van een transactie: SiteAuditCheckResult::create()
        // hieronder valt niet binnen een try/catch, en er is geen retry-guard
        // op deze job. Als er iets misgaat buiten een controle zelf (een
        // gekilled worker, een fout in AuditComparer, een mislukte update),
        // blijft de audit op analysing staan met bevindingen en
        // check-resultaten die al wel gecommit zijn. De volgende poging (een
        // automatische retry of een handmatige herstart) begint dan niet
        // schoon: hij zou elke controle die de vorige keer al slaagde
        // verdubbelen en daarna stuklopen op de unique key van
        // site_audit_check_results, ongevangen en zonder de logregel die de
        // per-controle catch hieronder wel zou schrijven. Een transactie zou
        // dit niet oplossen (een gekilde worker rolt niets terug, en een
        // transactie over 55 controles en duizenden rijen is bovendien duur),
        // dus in plaats daarvan ruimt elke poging eerst zijn eigen, mogelijk
        // halfslachtige eerdere rijen op voordat hij herbouwt.
        SiteAuditFinding::where('audit_id', $audit->id)->delete();
        SiteAuditCheckResult::where('audit_id', $audit->id)->delete();

        // De crawler laat to_page_id leeg omdat de doelpagina tijdens het
        // crawlen vaak nog geen rij is. broken_external_link,
        // internal_link_to_redirect en internal_link_to_4xx leunen hierop en
        // geven zonder deze twee aanroepen stilzwijgend niets terug in plaats
        // van een fout, dus dit moet voor de controlelus staan.
        LinkResolver::resolve($audit);
        ExternalLinkChecker::check($audit);

        $registry = app(SeoCheckRegistry::class);

        // Per controle het aantal verschillende getroffen pagina's, niet het
        // aantal bevindingen. Het referentierapport dat dit scherm kopieert
        // noemt die kolom "PAGINA'S", en een deel van de linkcontroles
        // rapporteert een bevinding per link: een pagina met drie kapotte
        // links zou anders driemaal zo zwaar meetellen als een pagina met
        // een enkel probleem. Een bevinding zonder pagina (sitebreed, zoals
        // een ontbrekend favicon) telt hoogstens een keer mee, ongeacht hoe
        // vaak zo'n sitebrede bevinding voor die controle voorkomt.
        $affectedPageCounts = [];
        // error_count/warning_count/notice_count zijn wat een lezer
        // bovenaan het rapport ziet, dus ze moeten optellen tot precies wat
        // site_audit_check_results.count betekent: verschillende pagina's,
        // niet bevindingsregels. Ze worden hieronder opgebouwd uit dezelfde
        // $count als de check-resultaatrij, nooit uit het aantal
        // weggeschreven rijen.
        $severityCounts = ['error' => 0, 'warning' => 0, 'notice' => 0];

        foreach ($registry->checks() as $check) {
            $rows = [];
            $affectedPages = [];
            $failed = false;

            try {
                foreach ($check->run($audit) as $finding) {
                    $rows[] = [
                        'audit_id' => $audit->id,
                        'check_key' => $finding->checkKey,
                        'section' => $finding->section,
                        'severity' => $finding->severity,
                        'page_id' => $finding->pageId,
                        'url' => $finding->url,
                        'context' => json_encode($finding->context),
                        'is_new' => false,
                    ];
                    $affectedPages[$finding->pageId ?? '__site__'] = true;

                    if (count($rows) >= 200) {
                        SiteAuditFinding::insert($rows);
                        $rows = [];
                    }
                }
            } catch (\Throwable $exception) {
                // Een controle die crasht mag de overige 54 niet meeslepen:
                // die hebben niets met dit probleem te maken en moeten
                // gewoon hun resultaat opleveren. Stilzwijgend doorgaan zou
                // de fout net zo goed verbergen als de hele beoordeling
                // laten vastlopen in analysing, dus loggen we hem en
                // behandelen we deze controle verder als "niets gevonden".
                Log::error(sprintf(
                    "SEO-controle '%s' is mislukt tijdens audit #%d: %s",
                    $check->key(),
                    $audit->id,
                    $exception->getMessage(),
                ), ['exception' => $exception]);

                $failed = true;
                $rows = [];
                $affectedPages = [];

                // Bevindingen die vóór de crash al zijn weggeschreven horen
                // niet te blijven staan naast een telling van nul: dat zou
                // een controle laten zien die zowel "niets gevonden" als wel
                // degelijk rijen in site_audit_findings heeft.
                SiteAuditFinding::where('audit_id', $audit->id)
                    ->where('check_key', $check->key())
                    ->delete();
            }

            if (! $failed && $rows !== []) {
                SiteAuditFinding::insert($rows);
            }

            $count = count($affectedPages);
            $affectedPageCounts[$check->key()] = $count;
            $severityCounts[$check->severity()] = ($severityCounts[$check->severity()] ?? 0) + $count;

            SiteAuditCheckResult::create([
                'audit_id' => $audit->id,
                'check_key' => $check->key(),
                'section' => $check->section(),
                'severity' => $check->severity(),
                'count' => $count,
            ]);

            $audit->update(['heartbeat_at' => now()]);
        }

        // Momentopname van de gewichten en van welke controles op dit
        // moment geregistreerd zijn (met hun ernst, want daar hangt het
        // gewicht van af). SeoScoreCalculator telt normaal over de levende
        // registry; zonder deze momentopname zou een controle die pas na
        // deze audit is toegevoegd het gewicht-totaal van dit oude rapport
        // alsnog laten meeschuiven bij een latere herberekening. De formule
        // zelf verandert hierdoor niet.
        $scoreWeights = config('dashed-seo.weights');
        $scoreCheckSeverities = [];
        foreach ($registry->checks() as $check) {
            $scoreCheckSeverities[$check->key()] = $check->severity();
        }

        AuditComparer::markNew($audit);

        // Zelfde eenheid als count hierboven: verschillende getroffen
        // pagina's, geen bevindingsregels. Een simpele ->count() op is_new
        // telt regels, en een controle die per link meldt (bv.
        // internal_link_to_4xx) kan dan meer nieuwe regels tonen dan er
        // ooit bevindingen in totaal zijn voor die controle. COALESCE vangt
        // een sitebrede bevinding zonder page_id op, die net als bij count
        // hoogstens een keer moet meetellen.
        $newCounts = SiteAuditFinding::where('audit_id', $audit->id)
            ->where('is_new', true)
            ->selectRaw('check_key, COUNT(DISTINCT COALESCE(page_id, -1)) as aantal')
            ->groupBy('check_key')
            ->pluck('aantal', 'check_key');

        foreach (SiteAuditCheckResult::where('audit_id', $audit->id)->get() as $result) {
            $result->update(['new_count' => (int) ($newCounts[$result->check_key] ?? 0)]);
        }

        foreach (AuditComparer::resolvedCounts($audit) as $checkKey => $resolved) {
            SiteAuditCheckResult::updateOrCreate(
                ['audit_id' => $audit->id, 'check_key' => $checkKey],
                [
                    'section' => $registry->check($checkKey)?->section() ?? 'onbekend',
                    'severity' => $registry->check($checkKey)?->severity() ?? 'notice',
                    'resolved_count' => $resolved,
                ]
            );
        }

        $audit->update([
            'status' => SiteAudit::STATUS_COMPLETED,
            'finished_at' => now(),
            'heartbeat_at' => now(),
            // Geen ondergrens op pages_crawled: SeoScoreCalculator geeft zelf
            // 100 terug zodra er niets gecrawld is (bijvoorbeeld deze job
            // handmatig aangeroepen voordat de crawler ooit klaar was). Een
            // ondergrens van 1 zou die eigen bodemwaarde omzeilen en één
            // toevallige bevinding laten uitrekenen alsof hij honderd
            // procent van de "pagina's" raakt.
            'health_score' => SeoScoreCalculator::calculate(
                $affectedPageCounts,
                (int) $audit->pages_crawled,
                $scoreWeights,
                $scoreCheckSeverities,
            ),
            'error_count' => $severityCounts['error'],
            'warning_count' => $severityCounts['warning'],
            'notice_count' => $severityCounts['notice'],
            'settings' => array_merge($audit->settings ?? [], [
                'score_weights' => $scoreWeights,
                'score_check_severities' => $scoreCheckSeverities,
            ]),
        ]);
    }
}
