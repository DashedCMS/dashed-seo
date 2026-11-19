<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Settings;

use Illuminate\Support\Facades\Crypt;
use Dashed\DashedCore\Models\Customsetting;

/**
 * Instellingen van de Search Console-koppeling, per site. Client-id en
 * client-secret zijn die van de Google-koppeling in core (Reviews), alleen
 * het refresh-token en de property zijn per site.
 */
class SearchConsoleSettings
{
    public const SCOPE = 'https://www.googleapis.com/auth/webmasters.readonly';

    public const OAUTH_FLOW = 'search-console';

    public const DEFAULT_DROP_THRESHOLD = 3;

    public const DEFAULT_MIN_IMPRESSIONS = 50;

    public static function enabled(?string $siteId): bool
    {
        return (bool) Customsetting::get('search_console_enabled', $siteId);
    }

    public static function property(?string $siteId): ?string
    {
        $value = trim((string) Customsetting::get('search_console_property', $siteId));

        return $value !== '' ? $value : null;
    }

    public static function refreshToken(?string $siteId): ?string
    {
        $stored = (string) Customsetting::get('search_console_refresh_token', $siteId);

        if ($stored === '') {
            return null;
        }

        return rescue(fn () => Crypt::decryptString($stored), null, report: false);
    }

    public static function storeRefreshToken(string $token, ?string $siteId): void
    {
        Customsetting::set('search_console_refresh_token', Crypt::encryptString($token), $siteId);
    }

    public static function forgetRefreshToken(?string $siteId): void
    {
        Customsetting::set('search_console_refresh_token', null, $siteId);
    }

    public static function connected(?string $siteId): bool
    {
        return self::refreshToken($siteId) !== null;
    }

    public static function clientConfigured(): bool
    {
        return (string) Customsetting::get('google_oauth_client_id') !== ''
            && (string) Customsetting::get('google_oauth_client_secret') !== '';
    }

    /** Klaar om op te halen: aan, gekoppeld en met een property. */
    public static function ready(?string $siteId): bool
    {
        return self::enabled($siteId) && self::connected($siteId) && self::property($siteId) !== null && self::clientConfigured();
    }

    /** Positieverlies (in plaatsen) vanaf waar een zoekterm een daler is. */
    public static function dropThreshold(?string $siteId): float
    {
        $value = (float) Customsetting::get('search_console_drop_threshold', $siteId, (string) self::DEFAULT_DROP_THRESHOLD);

        return $value > 0 ? $value : self::DEFAULT_DROP_THRESHOLD;
    }

    /** Minimaal aantal vertoningen in de laatste week voor een daler. */
    public static function minImpressions(?string $siteId): int
    {
        return max(0, (int) Customsetting::get('search_console_min_impressions', $siteId, (string) self::DEFAULT_MIN_IMPRESSIONS));
    }

    public static function alertsEnabled(?string $siteId): bool
    {
        return (bool) Customsetting::get('search_console_alerts_enabled', $siteId, '1');
    }
}
