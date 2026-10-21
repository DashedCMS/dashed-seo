<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\StructuredData;

use Dashed\DashedSeo\Models\SiteAudit;
use Dashed\DashedSeo\Models\SiteAuditPage;
use Dashed\DashedSeo\Crawler\StructuredData;

/**
 * Gedeeld door de schemacontroles: alleen pagina's waarvan de HTML gescand
 * is (structured_data is dan een array, ook als hij leeg is) tellen mee.
 * Een pagina uit een oudere scan, van voor deze kolom, heeft null en wordt
 * overgeslagen in plaats van als "zonder schema" gemeld.
 */
trait StructuredDataPages
{
    /**
     * @return iterable<SiteAuditPage>
     */
    protected function scannedPages(SiteAudit $audit): iterable
    {
        foreach ($this->indexablePages($audit)->whereNotNull('structured_data')->orderBy('id')->cursor() as $page) {
            if (is_array($page->structured_data)) {
                yield $page;
            }
        }
    }

    /**
     * @return array<string, int> familie => aantal topknopen op de pagina
     */
    protected function familyCounts(SiteAuditPage $page): array
    {
        return StructuredData::familyCounts($page->structured_data ?? []);
    }
}
