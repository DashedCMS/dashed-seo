<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Settings;

use Dashed\DashedSeo\SeoFixerRegistry;
use Dashed\DashedSeo\Models\SiteAuditFix;
use Dashed\DashedCore\Models\Customsetting;

/**
 * De standen van het automatisch oplossen, per site. Dezelfde vorm als
 * AuditSettings: alles via Customsetting, met een standaard die uit de code
 * komt en niet uit de database.
 */
final class FixSettings
{
    public const MODE_OFF = 'uit';
    public const MODE_SUGGESTION = 'voorstel';
    public const MODE_AUTO = 'automatisch';

    public const DEFAULT_AI_LIMIT = 50;

    /** @return array<int, string> */
    public static function modes(): array
    {
        return [self::MODE_OFF, self::MODE_SUGGESTION, self::MODE_AUTO];
    }

    public static function enabled(?string $siteId = null): bool
    {
        return (bool) self::setting('site_audit_fixes_enabled', $siteId, false);
    }

    public static function aiLimit(?string $siteId = null): int
    {
        return (int) self::setting('site_audit_fix_ai_limit', $siteId, self::DEFAULT_AI_LIMIT);
    }

    /**
     * De stand van één controle. Zonder ingestelde waarde geldt de
     * standaardstand van de fixer zelf, zodat een nieuwe fixer meteen de
     * bedoelde stand heeft zonder migratie van instellingen.
     */
    public static function modeFor(string $checkKey, ?string $siteId = null): string
    {
        $stored = (string) self::setting("site_audit_fix_mode_{$checkKey}", $siteId, '');

        if (in_array($stored, self::modes(), true)) {
            return $stored;
        }

        $fixer = app(SeoFixerRegistry::class)->fixerFor($checkKey);

        if (! $fixer) {
            return self::MODE_OFF;
        }

        return $fixer->defaultMode() === SiteAuditFix::MODE_AUTO
            ? self::MODE_AUTO
            : self::MODE_SUGGESTION;
    }

    protected static function setting(string $name, ?string $siteId, mixed $default): mixed
    {
        $value = Customsetting::get($name, $siteId);

        // Een leeg tekstveld komt als lege string terug, en dat is geen
        // ingestelde waarde maar een niet-ingevuld veld.
        return ($value === null || $value === '') ? $default : $value;
    }
}
