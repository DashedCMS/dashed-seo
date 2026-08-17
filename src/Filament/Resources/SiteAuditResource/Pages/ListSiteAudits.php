<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Filament\Resources\SiteAuditResource\Pages;

use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedSeo\Audits\AuditGuard;
use Filament\Resources\Pages\ListRecords;
use Dashed\DashedSeo\Filament\Actions\ScanNowAction;
use Dashed\DashedSeo\Filament\Resources\SiteAuditResource;

class ListSiteAudits extends ListRecords
{
    protected static string $resource = SiteAuditResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ScanNowAction::make(),
        ];
    }

    public function getSubheading(): ?string
    {
        $running = AuditGuard::running(Sites::getActive());

        if (! $running) {
            return null;
        }

        return __('Er loopt een scan: :status, :aantal pagina\'s van :gevonden gevonden adressen opgehaald.', [
            'status' => SiteAuditResource::statusLabel($running->status),
            'aantal' => $running->pages_crawled ?: 0,
            'gevonden' => $running->urls_found ?: 0,
        ]);
    }

    protected function getTablePollingInterval(): ?string
    {
        // Alleen verversen zolang er iets loopt: een stil overzicht hoeft
        // niet elke tien seconden de database te bevragen.
        return AuditGuard::running(Sites::getActive()) ? '10s' : null;
    }
}
