<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Audits;

use Filament\Facades\Filament;
use Dashed\DashedSeo\Models\SiteAuditPage;

/**
 * Van een gecrawlde URL naar het bewerkscherm van het CMS-model erachter.
 * De crawler heeft model_type en model_id al ingevuld; deze klasse zoekt de
 * Filament-resource erbij.
 */
final class PageEditLink
{
    public static function for(SiteAuditPage $page): ?string
    {
        if (! $page->model_type || ! $page->model_id || ! class_exists($page->model_type)) {
            return null;
        }

        foreach (Filament::getResources() as $resource) {
            if ($resource::getModel() !== $page->model_type) {
                continue;
            }

            // Niet elke resource heeft een bewerkscherm.
            if (! array_key_exists('edit', $resource::getPages())) {
                return null;
            }

            $record = $page->model_type::find($page->model_id);

            // Het model kan intussen verwijderd zijn, en een gebruiker zonder
            // bewerkrecht op dit record moet geen knop krijgen die toch op
            // Filament's eigen abort_unless(canEdit(...), 403) uitkomt.
            if (! $record || ! $resource::canEdit($record)) {
                return null;
            }

            try {
                return $resource::getUrl('edit', ['record' => $page->model_id]);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }
}
