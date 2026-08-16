<?php

declare(strict_types=1);

namespace Dashed\DashedSeo;

use Filament\Panel;
use Filament\Contracts\Plugin;
use Dashed\DashedSeo\Filament\Resources\SiteAuditResource;
use Dashed\DashedSeo\Filament\Pages\Settings\DashedSeoSettingsPage;

class DashedSeoPlugin implements Plugin
{
    public function getId(): string
    {
        return 'dashed-seo';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                SiteAuditResource::class,
            ])
            // Zonder deze regel bestaat de route van de instellingenpagina
            // niet, ook al staat hij wel in het instellingenoverzicht.
            ->pages([
                DashedSeoSettingsPage::class,
            ]);
    }

    public function boot(Panel $panel): void
    {
    }
}
