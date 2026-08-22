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
use Dashed\DashedSeo\Exceptions\FixStaleException;

/**
 * Een enkel voorstel doorvoeren. Eerst de site en de taal zetten, dan kijken
 * of het record nog is wat het was, dan pas schrijven.
 *
 * De verlooptoets, de schrijfactie en de statusovergang zitten samen in een
 * transactie, met een slot op de fixrij en op het record dat geschreven wordt.
 * Zonder dat slot kunnen twee workers twee fixen op dezelfde pagina oppakken,
 * allebei dezelfde inhoud lezen, allebei door de toets komen en allebei
 * schrijven: de laatste wint, een herschrijving gaat stil verloren, beide
 * rijen zeggen "Doorgevoerd" en terugdraaien van de eerste zet inhoud terug
 * van voor de tweede. Bulk toepassen en "Los alle N op" doen precies dit, en
 * deze installaties draaien Horizon met meerdere workers.
 */
class ApplySiteAuditFixJob implements ShouldQueue
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

        if (! $fix || $fix->status !== SiteAuditFix::STATUS_PENDING) {
            return;
        }

        $fixer = app(SeoFixerRegistry::class)->fixerFor($fix->check_key);

        if (! $fixer) {
            $fix->update([
                'status' => SiteAuditFix::STATUS_FAILED,
                'error' => __('Er is geen fixer meer geregistreerd voor :controle.', ['controle' => $fix->check_key]),
            ]);

            return;
        }

        $user = $this->userId ? User::find($this->userId) : null;

        if (! FixGuard::mayWrite($fix, $user, $fixer)) {
            // Wel een statuswijziging: stil terugkeren laat de knop een groene
            // bevestiging geven terwijl er niets gebeurt en er nergens een
            // spoor achterblijft. 'failed' is hier waar, want er is niets
            // geschreven. De rij herstelt zichzelf: alreadyKnown() telt
            // 'failed' niet mee, dus de volgende scan doet gewoon opnieuw een
            // voorstel, dat iemand met de juiste rechten wel kan goedkeuren.
            $fix->update([
                'status' => SiteAuditFix::STATUS_FAILED,
                'error' => __('Je hebt niet genoeg rechten om dit voorstel door te voeren.'),
            ]);

            return;
        }

        $oorspronkelijkeLocale = app()->getLocale();
        $vorigeSite = config('dashed-core.dashed_site_id');

        try {
            // De fixer leest CMS-modellen via Sites::getActive(), dus de site
            // moet vaststaan zolang deze fix wordt toegepast.
            config()->set('dashed-core.dashed_site_id', $fix->site_id);

            if ($fix->locale) {
                Locales::setLocale($fix->locale);
            }

            // Twee pogingen: bij bulk toepassen staan meerdere fixen op
            // dezelfde pagina tegelijk op de wachtrij, en dan kan het slot
            // hieronder in een wachttijd lopen. Dat is drukte, geen fout, en
            // zonder tweede poging landt zo'n fix op 'failed' met een rauwe
            // SQL-melding erin. Laravel doet alleen een tweede poging bij een
            // echte gelijktijdigheidsfout, dus een inhoudelijke uitzondering
            // uit de fixer wordt hier niet stilletjes herhaald.
            DB::transaction(function () use ($fixer): void {
                $fix = SiteAuditFix::query()->whereKey($this->fixId)->lockForUpdate()->first();

                // Een andere worker kan deze rij tussen het begin van de job en
                // dit slot al hebben afgehandeld.
                if (! $fix || $fix->status !== SiteAuditFix::STATUS_PENDING) {
                    return;
                }

                FixLock::onSubject($fix);

                if (SiteAuditFix::fingerprintFor(FixGuard::currentValues($fix)) !== $fix->fingerprint) {
                    $fix->update(['status' => SiteAuditFix::STATUS_STALE]);

                    return;
                }

                $fixer->apply($fix);

                $fix->update([
                    'status' => SiteAuditFix::STATUS_APPLIED,
                    'applied_at' => now(),
                    'applied_by' => $this->userId,
                    'error' => null,
                ]);
            }, 2);
        } catch (FixStaleException $e) {
            // Geen fout maar een achterhaald voorstel: een fixer die een record
            // aanmaakt in plaats van wijzigt heeft geen subject om de
            // vingerafdruk tegen af te zetten en kijkt daarom zelf.
            $fix->update([
                'status' => SiteAuditFix::STATUS_STALE,
                'error' => $e->getMessage(),
            ]);
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
