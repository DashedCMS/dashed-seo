<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Crawler;

class UrlNormalizer
{
    /** Parameters die dezelfde pagina opleveren en dus geen eigen URL zijn. */
    public const TRACKING_PARAMS = [
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
        'gclid', 'fbclid', 'msclkid', 'mc_cid', 'mc_eid', '_ga',
    ];

    public static function normalize(string $url, string $base): ?string
    {
        $url = trim($url);

        if ($url === '' || str_starts_with($url, '#')) {
            return null;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if ($scheme !== null && ! in_array(strtolower($scheme), ['http', 'https'], true)) {
            return null;
        }

        $absolute = $scheme === null ? self::resolve($url, $base) : $url;
        if ($absolute === null) {
            return null;
        }

        $parts = parse_url($absolute);
        if ($parts === false || ! isset($parts['host'])) {
            return null;
        }

        $path = $parts['path'] ?? '/';
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        $query = '';
        if (isset($parts['query'])) {
            parse_str($parts['query'], $params);
            $params = array_diff_key($params, array_flip(self::TRACKING_PARAMS));
            if ($params !== []) {
                ksort($params);
                $query = '?' . http_build_query($params);
            }
        }

        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        return strtolower($parts['scheme'] ?? 'https') . '://' . strtolower($parts['host']) . $port . $path . $query;
    }

    public static function isSameHost(string $url, string $base): bool
    {
        $a = parse_url($url, PHP_URL_HOST);
        $b = parse_url($base, PHP_URL_HOST);

        return $a !== null && $b !== null && strtolower($a) === strtolower($b);
    }

    public static function hash(string $url): string
    {
        return hash('sha256', $url);
    }

    protected static function resolve(string $relative, string $base): ?string
    {
        $parts = parse_url($base);
        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $root = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');

        if (str_starts_with($relative, '//')) {
            return $parts['scheme'] . ':' . $relative;
        }

        if (str_starts_with($relative, '/')) {
            return $root . $relative;
        }

        $basePath = $parts['path'] ?? '/';
        $directory = str_ends_with($basePath, '/') ? $basePath : dirname($basePath);
        $directory = $directory === '.' || $directory === DIRECTORY_SEPARATOR ? '/' : rtrim($directory, '/') . '/';

        return $root . $directory . $relative;
    }
}
