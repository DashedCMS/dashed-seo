<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Filament\Actions;

use Filament\Actions\Action;
use Dashed\DashedCore\Classes\Sites;
use Filament\Forms\Components\Select;
use Dashed\DashedSeo\Audits\AuditGuard;
use Filament\Notifications\Notification;
use Dashed\DashedSeo\Jobs\StartSiteAuditJob;
use Dashed\DashedSeo\Filament\Resources\SiteAuditResource;

/**
 * De knop "nu scannen", op het overzicht en op de instellingenpagina. Eén
 * plek, want beide knoppen moeten dezelfde beslissing nemen: de claim is
 * bindend, problem() is er alleen om de reden te kunnen tonen.
 */
final class ScanNowAction
{
    public static function make(string $name = 'scanNow'): Action
    {
        return Action::make($name)
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

                // De claim is de bindende beslissing, niet problem(): die is er
                // alleen om de reden te kunnen tonen. Zou de knop zelf op
                // problem() beslissen, dan zit er een gat tussen het kijken en
                // het aanmaken.
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
                    ->actions([
                        Action::make('bekijk')
                            ->label(__('Naar de sitescans'))
                            ->url(SiteAuditResource::getUrl('index')),
                    ])
                    ->send();
            });
    }
}
