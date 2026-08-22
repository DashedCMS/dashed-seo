<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Fixes\Meta;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;

/**
 * De pagina als platte tekst, voor in een prompt. Bewust uit het CMS-model en
 * niet uit de gecrawlde HTML: het model is de bron, en SiteAuditPage bewaart de
 * paginatekst helemaal niet.
 */
final class PageContext
{
    public const MAX_TEKENS = 1500;

    public static function for(Model $model, ?string $locale = null): string
    {
        $delen = [];

        foreach (['name', 'title'] as $veld) {
            $waarde = self::read($model, $veld, $locale);

            if (is_string($waarde) && $waarde !== '') {
                $delen[] = $waarde;

                break;
            }
        }

        $content = self::read($model, 'content', $locale);

        if (is_array($content)) {
            $delen[] = self::flatten($content);
        } elseif (is_string($content)) {
            $delen[] = $content;
        }

        $tekst = trim(strip_tags(implode("\n\n", array_filter($delen))));
        $tekst = (string) preg_replace('/\s+/u', ' ', $tekst);

        return Str::limit($tekst, self::MAX_TEKENS, '');
    }

    protected static function read(Model $model, string $veld, ?string $locale): mixed
    {
        if ($locale && method_exists($model, 'getTranslation') && in_array($veld, $model->translatable ?? [], true)) {
            return $model->getTranslation($veld, $locale);
        }

        return $model->{$veld} ?? null;
    }

    /**
     * Sleutels die in het editorformaat altijd structuur zijn en nooit
     * paginatekst, ongeacht op welk nesting-niveau ze voorkomen. 'type' is de
     * node- of marksoort ('doc', 'paragraph', 'text', 'link', 'codeBlock',
     * ...). De rest zijn attrs van marks en nodes: 'href'/'rel'/'target' van
     * een link-mark, 'language' van een codeBlock, 'id' van een kop of
     * anker, 'src' van een afbeelding. 'alt' staat er bewust niet bij: dat is
     * de alt-tekst van een afbeelding, en dat is wel echte paginatekst.
     *
     * @var array<int, string>
     */
    protected const STRUCTURELE_SLEUTELS = ['type', 'href', 'rel', 'target', 'language', 'id', 'src'];

    /**
     * Alle tekstwaarden uit een blokkenstructuur achter elkaar. Het
     * editorformaat kent zowel het oude blokformaat (`data.text`) als
     * geneste Tiptap-documenten (`type: doc` met `content` en losse
     * `text`-knopen, plus marks met `attrs`), en in beide vormen dragen de
     * sleutels in STRUCTURELE_SLEUTELS de structuur, niet de paginatekst.
     * Zonder die uitzondering komen woorden als "doc", "paragraph" en een
     * kale "_blank" of "noopener noreferrer nofollow" gewoon tussen de
     * echte zinnen te staan.
     */
    protected static function flatten(array $blokken): string
    {
        $tekst = [];

        array_walk_recursive($blokken, function ($waarde, $sleutel) use (&$tekst): void {
            if (in_array($sleutel, self::STRUCTURELE_SLEUTELS, true)) {
                return;
            }

            if (is_string($waarde) && trim(strip_tags($waarde)) !== '') {
                $tekst[] = $waarde;
            }
        });

        return implode(' ', $tekst);
    }
}
