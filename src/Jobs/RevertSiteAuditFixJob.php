<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Jobs;

use Illuminate\Bus\Queueable;
use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\DB;
use Dashed\DashedSeo\Fixes\FixLock;
use Dashed\DashedSeo\Fixes\FixGuard;
use Dashed\DashedCore\Classes\Locales;
use Dashed\DashedSeo\SeoFixerRegistry;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Dashed\DashedSeo\Models\SiteAuditFix;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Een doorgevoerde fix terugzetten naar de vastgelegde oude waarde.
 *
 * Zelfde vorm als ApplySiteAuditFixJob, en om dezelfde reden: het slot op het
 * record en de statusovergang horen binnen dezelfde transactie als de
 * schrijfactie, anders kan een tweede worker er tussendoor schrijven.
 */
class RevertSiteAuditFixJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 300;

    public function __construct(public int $fixId, public ?int $userId = null)
    {
    }

    public function handle(): void
    {
        $fix = SiteAuditFix::find($this->fixId);

        if (! $fix || $fix->status !== SiteAuditFix::STATUS_APPLIED) {
            return;
        }

        $fixer = app(SeoFixerRegistry::class)->fixerFor($fix->check_key);

        if (! $fixer || ! $fixer->supportsRevert()) {
            return;
        }

        $user = $this->userId ? User::find($this->userId) : null;

        if (! FixGuard::mayWrite($fix, $user, $fixer)) {
            // Wel een reden vastleggen, maar bewust niet de status aanraken.
            // De terugdraaiknop is alleen zichtbaar op een doorgevoerde fix,
            // dus zou 'failed' hier de enige weg terug wegnemen terwijl de
            // wijziging nog live op de site van de klant staat. Anders dan aan
            // de toepaskant, waar 'failed' waar is omdat er niets geschreven
            // is: hier is er wel degelijk iets geschreven en staat het er nog.
            // Zo blijft de knop staan voor iemand die er wel bij mag, en legt
            // de melding uit waarom de vorige poging niets deed.
            $fix->update([
                'error' => __('Je hebt niet genoeg rechten om dit voorstel terug te draaien.'),
            ]);

            return;
        }

        $oorspronkelijkeLocale = app()->getLocale();
        $vorigeSite = config('dashed-core.dashed_site_id');

        try {
            // De fixer leest CMS-modellen via Sites::getActive(), dus de site
            // moet vaststaan zolang deze fix wordt teruggedraaid.
            config()->set('dashed-core.dashed_site_id', $fix->site_id);

            if ($fix->locale) {
                Locales::setLocale($fix->locale);
            }

            // Twee pogingen: bij het terugdraaien van meerdere fixen op
            // dezelfde pagina kan het slot hieronder in een wachttijd lopen, en
            // dat is geen fout maar drukte. Laravel doet alleen een tweede
            // poging bij een echte gelijktijdigheidsfout, dus een inhoudelijke
            // uitzondering wordt hier niet stilletjes herhaald.
            DB::transaction(function () use ($fixer): void {
                $fix = SiteAuditFix::query()->whereKey($this->fixId)->lockForUpdate()->first();

                if (! $fix || $fix->status !== SiteAuditFix::STATUS_APPLIED) {
                    return;
                }

                FixLock::onSubject($fix);

                $fixer->revert($fix);

                $fix->update([
                    'status' => SiteAuditFix::STATUS_REVERTED,
                    'reverted_at' => now(),
                    'reverted_by' => $this->userId,
                    'error' => null,
                ]);
            }, 2);
        } catch (\Throwable $e) {
            $fix->update([
                'status' => SiteAuditFix::STATUS_FAILED,
                'error' => $e->getMessage(),
            ]);
        } finally {
            // Een queue-worker draait lang en pakt hierna een andere job op.
            // Zonder dit denkt die job dat de site van deze fix de actieve
            // site is.
            config()->set('dashed-core.dashed_site_id', $vorigeSite);
            Locales::setLocale($oorspronkelijkeLocale);
        }
    }
}
