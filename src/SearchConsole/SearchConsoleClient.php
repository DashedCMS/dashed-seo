<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\SearchConsole;

use RuntimeException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedSeo\Settings\SearchConsoleSettings;

/**
 * Praat met de Search Console API via Laravels HTTP-client, niet via de
 * Google-bibliotheek: die gebruikt Guzzle rechtstreeks en dan ziet
 * Http::fake() in de tests niets.
 */
class SearchConsoleClient
{
    public const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    public const API = 'https://www.googleapis.com/webmasters/v3';

    /** Het maximum dat de API per verzoek teruggeeft. */
    public const ROW_LIMIT = 25000;

    public function __construct(protected ?string $siteId)
    {
    }

    public function accessToken(): string
    {
        $refreshToken = SearchConsoleSettings::refreshToken($this->siteId);

        if (! $refreshToken) {
            throw new RuntimeException('Search Console is niet gekoppeld.');
        }

        $cacheKey = 'dashed-seo:search-console-token:' . $this->siteId . ':' . md5($refreshToken);

        $cached = Cache::get($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = Http::asForm()->timeout(30)->post(self::TOKEN_URL, [
            'client_id' => (string) Customsetting::get('google_oauth_client_id'),
            'client_secret' => (string) Customsetting::get('google_oauth_client_secret'),
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);

        $token = (string) $response->json('access_token');

        if ($response->failed() || $token === '') {
            throw new RuntimeException('Google gaf geen toegangstoken: ' . ($response->json('error_description') ?? $response->json('error') ?? $response->status()));
        }

        Cache::put($cacheKey, $token, max(60, (int) $response->json('expires_in', 3600) - 120));

        return $token;
    }

    /** @return array<int, string> de properties waar dit account bij kan */
    public function properties(): array
    {
        $response = Http::withToken($this->accessToken())->timeout(30)->get(self::API . '/sites');

        if ($response->failed()) {
            throw new RuntimeException('Search Console gaf een fout bij het ophalen van de properties: ' . $response->status());
        }

        return collect($response->json('siteEntry', []))
            ->reject(fn ($entry) => ($entry['permissionLevel'] ?? '') === 'siteUnverifiedUser')
            ->pluck('siteUrl')
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Eén pagina rijen voor één dag, met dimensies pagina en zoekterm.
     *
     * @return array<int, array{keys: array<int, string>, clicks: int|float, impressions: int|float, ctr: float, position: float}>
     */
    public function rowsForDate(string $property, string $date, int $startRow = 0): array
    {
        $response = Http::withToken($this->accessToken())
            ->timeout(60)
            ->post(self::API . '/sites/' . rawurlencode($property) . '/searchAnalytics/query', [
                'startDate' => $date,
                'endDate' => $date,
                'dimensions' => ['page', 'query'],
                'rowLimit' => self::ROW_LIMIT,
                'startRow' => $startRow,
                'type' => 'web',
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Search Console gaf een fout bij het ophalen van ' . $date . ': ' . $response->status() . ' ' . ($response->json('error.message') ?? ''));
        }

        return $response->json('rows', []);
    }
}
