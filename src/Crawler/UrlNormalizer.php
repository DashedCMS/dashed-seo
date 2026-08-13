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
        $path = self::collapsePath($path);

        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        $query = '';
        if (isset($parts['query'])) {
            $params = self::parseQuery($parts['query']);
            $params = array_diff_key($params, array_flip(self::TRACKING_PARAMS));
            if ($params !== []) {
                ksort($params);
                $query = '?' . self::buildQuery($params);
            }
        }

        $scheme = strtolower($parts['scheme'] ?? 'https');
        $host = strtolower($parts['host']);
        $port = '';
        if (isset($parts['port'])) {
            $defaultPort = $scheme === 'https' ? 443 : 80;
            if ($parts['port'] !== $defaultPort) {
                $port = ':' . $parts['port'];
            }
        }

        return $scheme . '://' . $host . $port . $path . $query;
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

    protected static function collapsePath(string $path): string
    {
        $segments = explode('/', $path);
        $result = [];

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                if (count($result) > 0) {
                    array_pop($result);
                }
            } else {
                $result[] = $segment;
            }
        }

        return '/' . implode('/', $result);
    }

    protected static function parseQuery(string $query): array
    {
        $params = [];
        $pairs = explode('&', $query);

        foreach ($pairs as $pair) {
            if ($pair === '') {
                continue;
            }

            $parts = explode('=', $pair, 2);
            $key = urldecode($parts[0]);
            $value = isset($parts[1]) ? urldecode($parts[1]) : '';

            $params[$key] = $value;
        }

        return $params;
    }

    protected static function buildQuery(array $params): string
    {
        $pairs = [];

        foreach ($params as $key => $value) {
            $pairs[] = rawurlencode($key) . '=' . rawurlencode($value);
        }

        return implode('&', $pairs);
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
