<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Dashed\DashedSeo\Models\SiteAudit;
use Dashed\DashedSeo\SeoFixerRegistry;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedSeo\Fixes\FixProposal;
use Dashed\DashedSeo\Contracts\SeoFixer;
use Illuminate\Queue\InteractsWithQueue;
use Dashed\DashedSeo\Models\SiteAuditFix;
use Dashed\DashedSeo\Settings\FixSettings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedSeo\Models\SiteAuditFinding;

/**
 * Loopt de bevindingen van een afgeronde scan langs en maakt er voorstellen
 * van. Wat op automatisch staat gaat meteen door naar ApplySiteAuditFixJob,
 * de rest blijft wachten op een mens.
 */
class ProposeSiteAuditFixesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 900;

    public function __construct(public int $auditId)
    {
    }

    public function handle(): void
    {
        $audit = SiteAudit::find($this->auditId);

        if (! $audit || ! FixSettings::enabled($audit->site_id)) {
            return;
        }

        $vorigeSite = config('dashed-core.dashed_site_id');

        try {
            // De fixers lezen CMS-modellen via Sites::getActive(), dus de site
            // moet vaststaan zolang deze ronde loopt.
            config()->set('dashed-core.dashed_site_id', $audit->site_id);

            $registry = app(SeoFixerRegistry::class);
            $aiLimit = FixSettings::aiLimit($audit->site_id);
            $aiUsed = 0;

            $checkKeys = SiteAuditFinding::where('audit_id', $audit->id)
                ->select('check_key')
                ->distinct()
                ->pluck('check_key');

            foreach ($checkKeys as $checkKey) {
                $fixer = $registry->fixerFor($checkKey);

                if (! $fixer) {
                    continue;
                }

                $mode = FixSettings::modeFor($checkKey, $audit->site_id);

                if ($mode === FixSettings::MODE_OFF) {
                    continue;
                }

                $findings = SiteAuditFinding::where('audit_id', $audit->id)
                    ->where('check_key', $checkKey)
                    ->orderBy('id');

                // Een sitebrede fixer lost alle bevindingen van deze controle in
                // een keer op, dus die krijgt er precies een aangeboden.
                if ($fixer->scope() === SeoFixer::SCOPE_SITE) {
                    $findings->limit(1);
                }

                foreach ($findings->cursor() as $finding) {
                    if ($fixer->usesAi() && $aiUsed >= $aiLimit) {
                        break;
                    }

                    $fixer->resetAiCalls();

                    $proposal = $this->propose($fixer, $finding, $checkKey);

                    // Het plafond telt aanroepen en geen voorstellen. Een fixer
                    // die de AI aanroept en daarna niets voorstelt heeft die
                    // aanroep wel gedaan, en die telt dus ook mee. Bewust hier
                    // en niet na de vroege terugkeer hieronder.
                    $aiUsed += $fixer->aiCallsMade();

                    if (! $proposal instanceof FixProposal) {
                        continue;
                    }

                    $fingerprint = SiteAuditFix::fingerprintFor($proposal->before);
                    $subjectType = $proposal->subject?->getMorphClass();
                    $subjectId = $proposal->subject ? (string) $proposal->subject->getKey() : null;

                    if (SiteAuditFix::alreadyKnown($checkKey, $subjectType, $subjectId, $fingerprint)) {
                        continue;
                    }

                    $fix = SiteAuditFix::create([
                        'audit_id' => $audit->id,
                        'site_id' => $audit->site_id,
                        'check_key' => $checkKey,
                        'page_id' => $finding->page_id,
                        // Een sitebrede fixer dekt alle bevindingen van zijn
                        // controle in een keer, dus die hoort bij geen enkele
                        // bevinding in het bijzonder. De rest wel, en het
                        // rapport heeft dat nodig om elke regel aan zijn eigen
                        // voorstel te koppelen.
                        'finding_id' => $fixer->scope() === SeoFixer::SCOPE_SITE ? null : $finding->id,
                        'subject_type' => $subjectType,
                        'subject_id' => $subjectId,
                        'locale' => $proposal->locale,
                        'mode' => $mode === FixSettings::MODE_AUTO
                            ? SiteAuditFix::MODE_AUTO
                            : SiteAuditFix::MODE_SUGGESTION,
                        'status' => SiteAuditFix::STATUS_PENDING,
                        'summary' => $proposal->summary,
                        'before' => $proposal->before,
                        'after' => $proposal->after,
                        'fingerprint' => $fingerprint,
                    ]);

                    if ($fix->mode === SiteAuditFix::MODE_AUTO) {
                        ApplySiteAuditFixJob::dispatch($fix->id);
                    }
                }
            }
        } finally {
            // Een queue-worker draait lang en pakt hierna een andere job op.
            // Zonder dit denkt die job dat de site van deze scan de actieve
            // site is.
            config()->set('dashed-core.dashed_site_id', $vorigeSite);
        }
    }

    /**
     * Een fixer die over zijn eigen voorstel struikelt mag de hele ronde niet
     * meeslepen. Denk aan een AI-aanroep die eruit klapt op een tijdslimiet:
     * dan is die ene bevinding niet op te lossen, de rest wel.
     */
    protected function propose(SeoFixer $fixer, SiteAuditFinding $finding, string $checkKey): ?FixProposal
    {
        try {
            return $fixer->propose($finding);
        } catch (\Throwable $e) {
            Log::error(sprintf(
                "Voorstel voor bevinding #%d ('%s') is mislukt: %s",
                $finding->id,
                $checkKey,
                $e->getMessage(),
            ), ['exception' => $e]);

            return null;
        }
    }
}
