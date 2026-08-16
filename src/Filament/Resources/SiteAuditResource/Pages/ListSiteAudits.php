<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Filament\Resources\SiteAuditResource\Pages;

use Filament\Actions\Action;
use Dashed\DashedCore\Classes\Sites;
use Filament\Forms\Components\Select;
use Dashed\DashedSeo\Audits\AuditGuard;
use Filament\Notifications\Notification;
use Dashed\DashedSeo\Jobs\StartSiteAuditJob;
use Filament\Resources\Pages\ListRecords;
use Dashed\DashedSeo\Filament\Resources\SiteAuditResource;

class ListSiteAudits extends ListRecords
{
    protected static string $resource = SiteAuditResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('scanNow')
                ->label(__('Nu scannen'))
                ->icon('heroicon-o-play')
                ->schema(Sites::getAmountOfSites() > 1 ? [
                    Select::make('site_id')
                        ->label(__('Welke site'))
                        ->options(collect(Sites::getSites())->mapWithKeys(
                            fn (array $site): array => [$site['id'] => ucfirst($site['name'])]
                        )->all())
                        ->default(Sites::getActive())
                        ->required(),
                ] : [])
                ->action(function (array $data): void {
                    $siteId = $data['site_id'] ?? Sites::getActive();

                    // De claim is de bindende beslissing, niet problem(): die
                    // is er alleen om de reden te kunnen tonen. Zou de knop
                    // zelf op problem() beslissen, dan zit er een gat tussen
                    // het kijken en het aanmaken.
                    $audit = AuditGuard::claim($siteId, 'manual');

                    if (! $audit) {
                        Notification::make()
                            ->title(__('De scan kon niet gestart worden'))
                            ->body(AuditGuard::problem($siteId) ?? __('Onbekende reden.'))
                            ->warning()
                            ->send();

                        return;
                    }

                    StartSiteAuditJob::dispatch($audit->id);

                    Notification::make()
                        ->title(__('De scan staat in de wachtrij'))
                        ->body(__('Ververs deze pagina over een paar minuten om de voortgang te zien.'))
                        ->success()
                        ->send();
                }),
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
