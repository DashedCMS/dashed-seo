<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Filament\Resources\SiteAuditResource\Pages;

use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Contracts\Support\Htmlable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Dashed\DashedSeo\Models\SiteAuditPage;
use Dashed\DashedSeo\Audits\PageEditLink;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Dashed\DashedSeo\Filament\Resources\SiteAuditResource;

class ListSiteAuditPages extends Page implements HasTable
{
    // Niet een eigen getypeerde eigenschap $record: Livewire geeft bij een
    // hervalidatie de sleutel terug in plaats van het model, en dan klapt de
    // toewijzing eruit op het type. Deze trait van Filament regelt het
    // ophalen en het vasthouden van de rij op de manier die Livewire aankan,
    // net als ViewSiteAuditReport.
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = SiteAuditResource::class;

    protected string $view = 'dashed-seo::filament.pages.audit-pages';

    public function mount(int | string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string | Htmlable
    {
        return __('Pagina\'s van de scan van :datum', [
            'datum' => $this->getRecord()->created_at?->format('d-m-Y H:i') ?? '',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('report')
                ->label(__('Terug naar het rapport'))
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn (): string => SiteAuditResource::getUrl('report', ['record' => $this->getRecord()])),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                SiteAuditPage::query()
                    ->where('audit_id', $this->getRecord()->id)
                    ->withCount('findings')
            )
            ->defaultSort('findings_count', 'desc')
            ->columns([
                TextColumn::make('url')
                    ->label(__('Adres'))
                    ->searchable()
                    ->limit(70)
                    ->url(fn (SiteAuditPage $record): string => $record->url)
                    ->openUrlInNewTab(),
                TextColumn::make('status_code')
                    ->label(__('Status'))
                    ->badge()
                    ->placeholder('-')
                    ->color(fn (?int $state): string => match (true) {
                        $state === null => 'gray',
                        $state >= 500 => 'danger',
                        $state >= 400 => 'danger',
                        $state >= 300 => 'warning',
                        default => 'success',
                    })
                    ->sortable(),
                TextColumn::make('title')
                    ->label(__('Titel'))
                    ->searchable()
                    ->limit(50)
                    ->placeholder(__('Geen titel')),
                TextColumn::make('meta_description')
                    ->label(__('Omschrijving'))
                    ->limit(50)
                    ->placeholder(__('Geen omschrijving'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('findings_count')
                    ->label(__('Problemen'))
                    ->sortable(),
                TextColumn::make('response_time_ms')
                    ->label(__('Reactietijd'))
                    ->formatStateUsing(fn (?int $state): string => $state === null ? '-' : __(':ms ms', ['ms' => $state]))
                    ->sortable(),
                TextColumn::make('word_count')
                    ->label(__('Woorden'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('crawl_status')
                    ->label(__('Opgehaald'))
                    ->options([
                        SiteAuditPage::CRAWL_DONE => __('Opgehaald'),
                        SiteAuditPage::CRAWL_ERROR => __('Mislukt'),
                        SiteAuditPage::CRAWL_PENDING => __('Niet aan toegekomen'),
                    ]),
                Filter::make('problems')
                    ->label(__('Alleen pagina\'s met problemen'))
                    ->query(fn (Builder $query): Builder => $query->having('findings_count', '>', 0)),
                Filter::make('blocked')
                    ->label(__('Door robots.txt afgeschermd'))
                    ->query(fn (Builder $query): Builder => $query->where('blocked_by_robots', true)),
            ])
            ->recordActions([
                Action::make('edit')
                    ->label(__('Bewerken'))
                    ->icon('heroicon-o-pencil-square')
                    // Alleen als de crawler het adres aan een CMS-model heeft
                    // kunnen koppelen; een externe of losse URL heeft geen
                    // bewerkscherm.
                    ->visible(fn (SiteAuditPage $record): bool => PageEditLink::for($record) !== null)
                    ->url(fn (SiteAuditPage $record): ?string => PageEditLink::for($record)),
            ])
            ->emptyStateHeading(__('Geen pagina\'s'))
            ->emptyStateDescription(__('Deze scan heeft geen pagina\'s meer, waarschijnlijk omdat hij buiten de bewaartermijn valt.'));
    }
}
