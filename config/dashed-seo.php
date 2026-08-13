<?php

return [
    'crawler' => [
        'max_pages' => 500,
        'max_depth' => 5,
        'delay_ms' => 200,
        'timeout' => 15,
        'batch_size' => 10,
        'max_external_links' => 200,
        // Bovengrens op het aantal bronbestanden (css/js/afbeeldingen) dat een
        // enkele audit ophaalt, over alle batches heen. Zonder plafond is een
        // volledige-body GET op elk distinct bronbestand van een shop met
        // duizenden productafbeeldingen een denial-of-service tegen de eigen
        // origin van de klant.
        'max_assets' => 300,
        'user_agent' => 'DashedSEO/1.0 (+https://dashed.nl)',
        // Header die de origin-cache omzeilt, zodat de audit de huidige staat
        // meet en niet een gecachte versie van een net gewijzigde pagina.
        'bypass_cache_header' => 'X-Dashed-Cache-Bypass',
    ],

    'thresholds' => [
        'title_max' => 70,
        'title_min' => 30,
        'description_max' => 160,
        'min_words' => 300,
        'slow_response_ms' => 1000,
        'html_max_bytes' => 200 * 1024,
        'css_max_bytes' => 150 * 1024,
        'js_max_bytes' => 300 * 1024,
        'max_redirect_hops' => 2,
    ],

    'weights' => [
        'error' => 10,
        'warning' => 3,
        'notice' => 1,
    ],

    'retention' => [
        'keep_full_audits' => 10,
        'stale_after_minutes' => 30,
    ],
];
