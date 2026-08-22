<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Fixes;

use Dashed\DashedSeo\Models\SiteAuditFix;

/**
 * Het record dat een fix aanraakt op slot zetten binnen de lopende transactie.
 *
 * Twee fixen op dezelfde pagina zijn de normale gang van zaken: drie kapotte
 * links op een pagina leveren drie bevindingen en dus drie fixen met hetzelfde
 * subject. Serieel gaat dat goed, want de tweede en de derde verlopen dan
 * terecht op de vingerafdruk. Parallel niet: twee workers lezen dezelfde
 * inhoud, komen allebei door de verlooptoets en schrijven allebei, waarna de
 * laatste wint en de eerste herschrijving stil verloren gaat.
 *
 * Een slot op de subjectrij laat de tweede worker wachten tot de eerste klaar
 * is, zodat die daarna de nieuwe inhoud leest en netjes verloopt. Het verse
 * exemplaar wordt meteen als relatie op de fix gezet, zodat de verlooptoets en
 * de schrijfactie precies dat exemplaar gebruiken en niet een kopie van voor
 * het slot.
 */
final class FixLock
{
    public static function onSubject(SiteAuditFix $fix): void
    {
        $type = $fix->subject_type;

        if (! $type || ! $fix->subject_id || ! class_exists($type)) {
            return;
        }

        $vers = $type::query()->whereKey($fix->subject_id)->lockForUpdate()->first();

        if ($vers) {
            $fix->setRelation('subject', $vers);
        }
    }
}
