<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Crawler;

/**
 * Wat de scan van de JSON-LD op een pagina onthoudt. Niet het hele document
 * (een productgroep met honderd varianten is tientallen kilobytes per
 * pagina), maar per blok of het parset en per knoop het type en de namen
 * van de gevulde eigenschappen. Daar kunnen de controles alles mee: welk
 * type ontbreekt, welk type dubbel staat en welke verplichte eigenschap
 * leeg is.
 */
class StructuredData
{
    /**
     * @return array{valid: bool, error: ?string, nodes: array<int, array{type: string, properties: array<int, string>}>}
     */
    public static function summarize(string $json): array
    {
        $decoded = json_decode(trim($json), true);

        if (! is_array($decoded)) {
            return ['valid' => false, 'error' => json_last_error() === JSON_ERROR_NONE ? 'Geen object' : json_last_error_msg(), 'nodes' => []];
        }

        $nodes = [];
        foreach (static::nodesOf($decoded) as $node) {
            foreach ((array) ($node['@type'] ?? []) as $type) {
                if (! is_string($type) || $type === '') {
                    continue;
                }

                $nodes[] = [
                    'type' => $type,
                    'properties' => array_values(array_filter(
                        array_keys($node),
                        fn ($key) => is_string($key) && ! str_starts_with($key, '@') && static::filled($node[$key]),
                    )),
                ];
            }
        }

        if ($nodes === []) {
            return ['valid' => false, 'error' => 'Geen @type', 'nodes' => []];
        }

        return ['valid' => true, 'error' => null, 'nodes' => $nodes];
    }

    /**
     * Subtypen tellen als hun ouder: een OnlineStore is de Organization van
     * de site, een BlogPosting een Article. Kleine letters, zodat een
     * vergelijking niet op hoofdletters struikelt.
     */
    public static function family(string $type): string
    {
        $type = strtolower($type);

        return match ($type) {
            'onlinestore', 'localbusiness', 'store', 'corporation', 'ngo' => 'organization',
            'blogposting', 'newsarticle', 'techarticle', 'scholarlyarticle' => 'article',
            default => $type,
        };
    }

    /**
     * Hoe vaak elke familie als topknoop op de pagina staat.
     *
     * @param  array<int, array{valid: bool, error: ?string, nodes: array}>  $blocks
     * @return array<string, int>
     */
    public static function familyCounts(array $blocks): array
    {
        $counts = [];

        foreach ($blocks as $block) {
            foreach ($block['nodes'] ?? [] as $node) {
                $family = static::family((string) ($node['type'] ?? ''));
                $counts[$family] = ($counts[$family] ?? 0) + 1;
            }
        }

        return $counts;
    }

    protected static function nodesOf(array $decoded): array
    {
        if (isset($decoded['@graph']) && is_array($decoded['@graph'])) {
            return array_filter($decoded['@graph'], 'is_array');
        }

        return array_is_list($decoded) ? array_filter($decoded, 'is_array') : [$decoded];
    }

    protected static function filled(mixed $value): bool
    {
        if (is_array($value)) {
            return $value !== [];
        }

        return $value !== null && trim((string) $value) !== '';
    }
}
