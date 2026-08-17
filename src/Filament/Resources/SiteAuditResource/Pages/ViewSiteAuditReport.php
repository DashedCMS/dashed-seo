<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Filament\Resources\SiteAuditResource\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Dashed\DashedSeo\Filament\Support\ReportData;
use Dashed\DashedSeo\Filament\Resources\SiteAuditResource;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;

class ViewSiteAuditReport extends Page
{
    // Niet een eigen getypeerde eigenschap $record: Livewire geeft bij een
    // hervalidatie de sleutel terug in plaats van het model, en dan klapt de
    // toewijzing eruit op het type. Deze trait van Filament regelt het
    // ophalen en het vasthouden van de rij op de manier die Livewire aankan.
    use InteractsWithRecord;

    protected static string $resource = SiteAuditResource::class;

    protected string $view = 'dashed-seo::filament.pages.report';

    public function mount(int | string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string | Htmlable
    {
        return __('Sitescan van :datum', [
            'datum' => $this->getRecord()->created_at?->format('d-m-Y H:i') ?? '',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pages')
                ->label(__('Per pagina bekijken'))
                ->icon('heroicon-o-table-cells')
                ->url(fn (): string => SiteAuditResource::getUrl('pages', ['record' => $this->getRecord()])),
        ];
    }

    /** Welke controle op dit moment uitgeklapt is, of null. */
    public ?string $openCheck = null;

    /** @return array<string, mixed> */
    public function cover(): array
    {
        return ReportData::cover($this->getRecord());
    }

    /** @return array<int, array<string, mixed>> */
    public function sections(): array
    {
        return ReportData::sections($this->getRecord());
    }

    public function toggleCheck(string $checkKey): void
    {
        // Een enkele controle tegelijk open: de lijsten kunnen honderd rijen
        // lang zijn en alles tegelijk uitklappen maakt de pagina onbruikbaar.
        $this->openCheck = $this->openCheck === $checkKey ? null : $checkKey;
    }

    /** @return array<string, mixed>|null */
    public function openFindings(): ?array
    {
        return $this->openCheck ? ReportData::findings($this->getRecord(), $this->openCheck) : null;
    }
}
