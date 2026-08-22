<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Fixes\Sitemap;

use Illuminate\Support\Facades\File;
use Dashed\DashedSeo\Fixes\BaseFixer;
use Dashed\DashedSeo\Fixes\FixProposal;
use Dashed\DashedSeo\Models\SiteAuditFix;
use Dashed\DashedSeo\Models\SiteAuditFinding;

/**
 * Een Sitemap-regel in robots.txt. Let op: dat is een bestand in public/ en
 * dus een voor alle sites samen, en een deploy kan het terugzetten. Dat staat
 * ook in de helptekst bij deze controle.
 */
class SitemapInRobotsFixer extends BaseFixer
{
    public function isSiteWide(): bool
    {
        return true;
    }

    public function checkKeys(): array
    {
        return ['sitemap_not_in_robots'];
    }

    public function scope(): string
    {
        return self::SCOPE_SITE;
    }

    public function defaultMode(): string
    {
        return SiteAuditFix::MODE_AUTO;
    }

    public function label(): string
    {
        return __('Sitemap noemen in robots.txt');
    }

    public function propose(SiteAuditFinding $finding): ?FixProposal
    {
        $bestand = public_path('robots.txt');
        $inhoud = file_exists($bestand) ? File::get($bestand) : '';

        $regel = 'Sitemap: ' . rtrim((string) config('app.url'), '/') . '/sitemap.xml';

        if (str_contains($inhoud, $regel)) {
            return null;
        }

        return new FixProposal(
            subject: null,
            locale: null,
            before: ['regel' => null],
            after: ['regel' => $regel],
            summary: __('De regel ":regel" toevoegen aan robots.txt', ['regel' => $regel]),
        );
    }

    public function apply(SiteAuditFix $fix): void
    {
        $bestand = public_path('robots.txt');
        $inhoud = file_exists($bestand) ? rtrim(File::get($bestand), "\n") : '';
        $regel = (string) ($fix->after['regel'] ?? '');

        if ($regel === '' || str_contains($inhoud, $regel)) {
            return;
        }

        File::put($bestand, $inhoud . "\n" . $regel . "\n");
    }

    public function revert(SiteAuditFix $fix): void
    {
        $bestand = public_path('robots.txt');

        if (! file_exists($bestand)) {
            return;
        }

        $regel = (string) ($fix->after['regel'] ?? '');

        if ($regel === '') {
            return;
        }

        // Alleen de regel weghalen die deze fix heeft toegevoegd, niet het
        // hele bestand terugzetten: er kan sinds het toepassen iets anders
        // aan robots.txt zijn veranderd dat wel moet blijven staan.
        $regels = array_filter(
            explode("\n", File::get($bestand)),
            fn (string $r): bool => trim($r) !== $regel,
        );

        File::put($bestand, rtrim(implode("\n", $regels), "\n") . "\n");
    }
}
