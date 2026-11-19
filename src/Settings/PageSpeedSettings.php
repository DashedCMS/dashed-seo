<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Settings;

use Dashed\DashedCore\Models\Customsetting;

class PageSpeedSettings
{
    public const DEFAULT_ALERT_DROP = 10;

    /** Hooguit zoveel URL's per site, mobiel en desktop samen het dubbele. */
    public const MAX_URLS = 10;

    public static function enabled(?string $siteId): bool
    {
        return (bool) Customsetting::get('pagespeed_enabled', $siteId);
    }

    public static function apiKey(?string $siteId): ?string
    {
        $key = trim((string) Customsetting::get('pagespeed_api_key', $siteId));

        return $key !== '' ? $key : null;
    }

    public static function ready(?string $siteId): bool
    {
        return self::enabled($siteId) && self::apiKey($siteId) !== null;
    }

    public static function autoUrls(?string $siteId): bool
    {
        return (bool) Customsetting::get('pagespeed_auto_urls', $siteId, '1');
    }

    /** @return array<int, string> de extra URL's die een beheerder invulde */
    public static function extraUrls(?string $siteId): array
    {
        $raw = (string) Customsetting::get('pagespeed_urls', $siteId);

        return collect(preg_split('/\R/', $raw) ?: [])
            ->map(fn ($url) => trim((string) $url))
            ->filter(fn ($url) => preg_match('#^https?://#i', $url))
            ->unique()
            ->values()
            ->all();
    }

    /** Daling van de prestatiescore (punten) vanaf waar een melding komt. */
    public static function alertDrop(?string $siteId): int
    {
        $value = (int) Customsetting::get('pagespeed_alert_drop', $siteId, (string) self::DEFAULT_ALERT_DROP);

        return $value > 0 ? $value : self::DEFAULT_ALERT_DROP;
    }
}
