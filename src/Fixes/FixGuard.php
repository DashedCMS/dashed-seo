<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Fixes;

use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\Gate;
use Dashed\DashedSeo\Contracts\SeoFixer;
use Dashed\DashedSeo\Models\SiteAuditFix;

/**
 * De twee controles die tussen een voorstel en een schrijfactie horen: is het
 * record intussen veranderd, en mag deze gebruiker er uberhaupt aan komen.
 */
final class FixGuard
{
    /**
     * De huidige waarden van precies de velden die deze fix aanraakt. Zonder
     * subject valt hij terug op de vastgelegde oude waarde, want dan is er
     * niets om tegen af te zetten en mag de fix niet onterecht verlopen.
     *
     * @return array<string, mixed>
     */
    public static function currentValues(SiteAuditFix $fix): array
    {
        $subject = $fix->subject;

        if (! $subject) {
            return $fix->before ?? [];
        }

        $values = [];

        foreach (array_keys($fix->before ?? []) as $field) {
            // Een sleutel die geen kolom is (bijvoorbeeld een vlag als
            // 'verwijderd' bij RemoveRedirectLoopFixer) telt niet mee in de
            // verlooptoets: er is niets op het model om tegen af te zetten, en
            // hem toch uitlezen levert null op waardoor de fix altijd verloopt.
            $vertaalbaar = in_array($field, $subject->translatable ?? [], true);

            if (! $vertaalbaar && ! array_key_exists($field, $subject->getAttributes())) {
                $values[$field] = $fix->before[$field];

                continue;
            }

            $values[$field] = ($fix->locale && $vertaalbaar && method_exists($subject, 'getTranslation'))
                ? $subject->getTranslation($field, $fix->locale)
                : $subject->{$field};
        }

        return $values;
    }

    /**
     * Mag deze gebruiker dit record aanpassen? Filament's eigen canEdit() kan
     * hier niet: die vraagt altijd de sessiegebruiker op
     * (Filament::auth()->user()), en een queue-worker heeft geen sessie en
     * geen paneel. Deze methode herhaalt daarom Filament's eigen regel, maar
     * dan geevalueerd op de gebruiker die is meegegeven in plaats van op de
     * sessie.
     *
     * Zonder gebruiker draait de fix automatisch in een job, en dan is de
     * hoofdschakelaar de toestemming.
     */
    public static function mayWrite(SiteAuditFix $fix, ?User $user, ?SeoFixer $fixer = null): bool
    {
        if (! $user) {
            return true;
        }

        if ($fixer?->isSiteWide()) {
            // De sitemap opnieuw bouwen of een regel in robots.txt zetten geldt
            // voor alles wat er staat, dus daar is alleen een superbeheerder
            // voor. Bewust deze kolom en niet hasRole(): dit project heeft geen
            // rollenpakket op User, en de twee plekken die hasRole() wel
            // aanroepen zetten er method_exists() voor.
            //
            // Bewust de uitspraak van de fixer en niet een leeg subject als
            // maatstaf: MissingPageRedirectFixer heeft bij het voorstellen ook
            // geen subject en maakt toch maar een enkele redirectrij aan.
            return $user->role === 'superadmin';
        }

        $subject = $fix->subject;

        if (! $subject) {
            // Geen subject en niet sitebreed: het record bestaat op dit moment
            // simpelweg nog niet, want deze fix maakt hem pas bij toepassen
            // aan. Er valt dan wel degelijk iets te vragen, namelijk het
            // create-recht op de klasse die hij gaat aanmaken. Dat is precies
            // de vraag die het bewerkscherm van dat model ook stelt
            // (BaseResourcePolicy::create() in dashed-core toetst daar
            // 'edit_redirect'). Zonder deze controle zou iedereen die dit
            // scherm bereikt records kunnen laten aanmaken die hij via zijn
            // eigen resource niet mag aanmaken.
            $klasse = $fixer?->createsModel();

            if ($klasse === null) {
                return true;
            }

            return Gate::forUser($user)->allows('create', $klasse);
        }

        // Filament's eigen regel, maar dan op de gebruiker die deze fix
        // goedkeurt in plaats van op de sessiegebruiker. Een queue-worker
        // heeft geen sessie en geen paneel, dus Resource::canEdit() zou hier
        // altijd weigeren. Zie vendor/filament/filament/src/helpers.php.
        $policy = Gate::getPolicyFor($subject);

        if (! $policy || ! method_exists($policy, 'update')) {
            // Geen policy betekent in dit CMS dat het model niet apart is
            // afgeschermd, en Filament laat het dan ook door. Strenger zijn
            // dan het bewerkscherm zelf zou elke meta-fix op een pagina
            // blokkeren voor gewone beheerders.
            return true;
        }

        return Gate::forUser($user)->allows('update', $subject);
    }
}
