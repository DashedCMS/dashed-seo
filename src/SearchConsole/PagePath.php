<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\SearchConsole;

/**
 * Herleidt een URL tot het pad waarop rijen uit Search Console worden
 * opgeslagen en gezocht: zonder schema, host, query en fragment, zonder slash
 * aan het eind (behalve de homepage) en in kleine letters. Zo vindt het
 * bewerkscherm de rijen van een pagina terug, of getUrl() nu een relatief of
 * een absoluut adres geeft.
 */
class PagePath
{
    public static function from(string $url): string
    {
        $path = parse_url(trim($url), PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            $path = '/';
        }

        $path = '/' . trim(rawurldecode($path), '/');

        return mb_substr(mb_strtolower($path), 0, 500);
    }
}
