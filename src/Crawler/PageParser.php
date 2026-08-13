<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Crawler;

use DOMText;
use DOMXPath;
use DOMElement;
use DOMDocument;

class PageParser
{
    protected const BLOCK_LEVEL_ELEMENTS = [
        'address', 'article', 'aside', 'blockquote', 'br', 'dd', 'div', 'dl',
        'dt', 'fieldset', 'figcaption', 'figure', 'footer', 'form', 'h1', 'h2',
        'h3', 'h4', 'h5', 'h6', 'header', 'hr', 'li', 'main', 'nav', 'ol', 'p',
        'pre', 'section', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr', 'ul',
    ];

    public static function parse(string $html, string $url): ParsedPage
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($document);
        $page = new ParsedPage();

        $page->title = self::text($xpath, '//head/title');
        $page->metaDescription = self::attribute($xpath, '//meta[translate(@name, "DESCRIPTION", "description")="description"]', 'content');
        $page->canonical = self::attribute($xpath, '//link[@rel="canonical"]', 'href');
        $page->robotsMeta = self::attribute($xpath, '//meta[translate(@name, "ROBOTS", "robots")="robots"]', 'content');
        $page->htmlLang = self::attribute($xpath, '//html', 'lang');

        foreach ($xpath->query('//h1') as $node) {
            $page->h1s[] = trim($node->textContent);
        }

        foreach (['h1', 'h2', 'h3', 'h4', 'h5', 'h6'] as $tag) {
            $page->headingCounts[$tag] = $xpath->query("//{$tag}")->length;
        }

        $body = $xpath->query('//body')->item(0);
        $pieces = [];
        if ($body !== null) {
            self::collectText($body, $pieces);
        }
        $text = trim(preg_replace('/\s+/u', ' ', implode('', $pieces)));
        $page->wordCount = $text === '' ? 0 : count(preg_split('/\s+/u', $text));

        foreach ($xpath->query('//script[@src]') as $node) {
            /** @var DOMElement $node */
            $resolved = UrlNormalizer::normalize($node->getAttribute('src'), $url);
            if ($resolved !== null) {
                $page->scripts[] = $resolved;
            }
        }

        foreach ($xpath->query('//link[@rel="alternate"][@hreflang]') as $node) {
            /** @var DOMElement $node */
            $page->hreflangs[] = [
                'lang' => $node->getAttribute('hreflang'),
                'url' => $node->getAttribute('href'),
            ];
        }

        foreach ($xpath->query('//img[@src][not(ancestor::noscript) and not(ancestor::script) and not(ancestor::style)]') as $node) {
            /** @var DOMElement $node */
            $resolved = UrlNormalizer::normalize($node->getAttribute('src'), $url);
            $page->images[] = [
                'src' => $resolved ?? $node->getAttribute('src'),
                'alt' => $node->hasAttribute('alt') ? $node->getAttribute('alt') : null,
            ];
        }

        foreach ($xpath->query('//link[@rel="stylesheet"][@href][not(ancestor::noscript) and not(ancestor::script) and not(ancestor::style)]') as $node) {
            /** @var DOMElement $node */
            $resolved = UrlNormalizer::normalize($node->getAttribute('href'), $url);
            if ($resolved !== null) {
                $page->stylesheets[] = $resolved;
            }
        }

        foreach ($xpath->query('//a[@href][not(ancestor::noscript) and not(ancestor::script) and not(ancestor::style)]') as $node) {
            /** @var DOMElement $node */
            $resolved = UrlNormalizer::normalize($node->getAttribute('href'), $url);
            if ($resolved === null) {
                continue;
            }

            $rel = $node->getAttribute('rel');
            $page->links[] = [
                'url' => $resolved,
                'anchor' => trim($node->textContent),
                'rel' => $rel === '' ? null : $rel,
                'is_image_link' => $node->getElementsByTagName('img')->length > 0,
            ];
        }

        return $page;
    }

    protected static function text(DOMXPath $xpath, string $query): ?string
    {
        $node = $xpath->query($query)->item(0);

        return $node ? trim($node->textContent) : null;
    }

    protected static function attribute(DOMXPath $xpath, string $query, string $attribute): ?string
    {
        $node = $xpath->query($query)->item(0);
        if (! $node instanceof DOMElement || ! $node->hasAttribute($attribute)) {
            return null;
        }

        $value = trim($node->getAttribute($attribute));

        return $value === '' ? null : $value;
    }

    protected static function collectText($node, array &$pieces): void
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                $pieces[] = $child->data;

                continue;
            }

            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->nodeName);

            if (in_array($tag, ['script', 'style', 'noscript'], true)) {
                continue;
            }

            $isBlock = in_array($tag, self::BLOCK_LEVEL_ELEMENTS, true);

            if ($isBlock) {
                $pieces[] = "\n";
            }

            self::collectText($child, $pieces);

            if ($isBlock) {
                $pieces[] = "\n";
            }
        }
    }
}
