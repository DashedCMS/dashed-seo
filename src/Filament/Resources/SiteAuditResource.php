<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Filament\Resources;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedSeo\Models\SiteAudit;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Dashed\DashedSeo\Filament\Resources\SiteAuditResource\Pages\ListSiteAudits;
use Dashed\DashedSeo\Filament\Resources\SiteAuditResource\Pages\ListSiteAuditPages;
use Dashed\DashedSeo\Filament\Resources\SiteAuditResource\Pages\ViewSiteAuditReport;

class SiteAuditResource extends Resource
{
    use \Dashed\DashedCore\Filament\Concerns\TranslatesResourceLabels;

    protected static ?string $model = SiteAudit::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-magnifying-glass-circle';

    protected static string | UnitEnum | null $navigationGroup = 'SEO & site';

    protected static ?string $navigationLabel = 'Sitescans';

    protected static ?string $modelLabel = 'sitescan';

    protected static ?string $pluralModelLabel = 'sitescans';

    protected static ?int $navigationSort = 10;

    public static function canCreate(): bool
    {
        // Een scan ontstaat door hem te draaien, niet door een formulier in te
        // vullen. De knop bovenaan het overzicht is de enige weg naar binnen.
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('Gestart op'))
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
                TextColumn::make('site_id')
                    ->label(__('Site'))
                    ->formatStateUsing(fn (?string $state): string => ucfirst(Sites::get($state)['name'] ?? (string) $state))
                    ->visible(Sites::getAmountOfSites() > 1),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::statusLabel($state))
                    ->color(fn (string $state): string => match ($state) {
                        SiteAudit::STATUS_COMPLETED => 'success',
                        SiteAudit::STATUS_FAILED => 'danger',
                        SiteAudit::STATUS_CANCELLED => 'gray',
                        default => 'warning',
                    }),
                TextColumn::make('health_score')
                    ->label(__('Technische score'))
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('error_count')->label(__('Fouten'))->placeholder('0'),
                TextColumn::make('warning_count')->label(__('Waarschuwingen'))->placeholder('0'),
                TextColumn::make('notice_count')->label(__('Notities'))->placeholder('0'),
                TextColumn::make('pages_crawled')->label(__('Pagina\'s'))->placeholder('0'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(fn (): array => collect([
                        SiteAudit::STATUS_PENDING,
                        SiteAudit::STATUS_CRAWLING,
                        SiteAudit::STATUS_ANALYSING,
                        SiteAudit::STATUS_COMPLETED,
                        SiteAudit::STATUS_FAILED,
                        SiteAudit::STATUS_CANCELLED,
                    ])->mapWithKeys(fn (string $s): array => [$s => self::statusLabel($s)])->all()),
            ])
            ->recordActions([
                Action::make('report')
                    ->label(__('Rapport'))
                    ->icon('heroicon-o-document-chart-bar')
                    ->visible(fn (SiteAudit $record): bool => $record->status === SiteAudit::STATUS_COMPLETED)
                    ->url(fn (SiteAudit $record): string => static::getUrl('report', ['record' => $record])),
                Action::make('cancel')
                    ->label(__('Afbreken'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    // Spiegelbeeld van SiteAudit::cancel(): die weigert op
                    // precies deze statusverzameling, dus deze knop verbergt
                    // zich op dezelfde verzameling.
                    ->visible(fn (SiteAudit $record): bool => $record->isRunning())
                    ->action(fn (SiteAudit $record) => $record->cancel()),
            ])
            ->emptyStateHeading(__('Nog geen sitescans'))
            ->emptyStateDescription(__('Start een scan om te zien wat er technisch mis is met de site.'));
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            SiteAudit::STATUS_PENDING => __('In de wachtrij'),
            SiteAudit::STATUS_CRAWLING => __('Bezig met ophalen'),
            SiteAudit::STATUS_ANALYSING => __('Bezig met beoordelen'),
            SiteAudit::STATUS_COMPLETED => __('Afgerond'),
            SiteAudit::STATUS_FAILED => __('Mislukt'),
            SiteAudit::STATUS_CANCELLED => __('Afgebroken'),
            default => $status,
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSiteAudits::route('/'),
            'report' => ViewSiteAuditReport::route('/{record}/rapport'),
            'pages' => ListSiteAuditPages::route('/{record}/paginas'),
        ];
    }
}
