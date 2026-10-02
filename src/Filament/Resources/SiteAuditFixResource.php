<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Filament\Resources;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Resources\Resource;
use Illuminate\Support\Collection;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedSeo\SeoCheckRegistry;
use Dashed\DashedSeo\SeoFixerRegistry;
use Filament\Tables\Columns\TextColumn;
use Dashed\DashedSeo\Models\SiteAuditFix;
use Filament\Tables\Filters\SelectFilter;
use Dashed\DashedSeo\Jobs\ApplySiteAuditFixJob;
use Dashed\DashedSeo\Jobs\RevertSiteAuditFixJob;
use Dashed\DashedSeo\Filament\Resources\SiteAuditFixResource\Pages\ListSiteAuditFixes;

class SiteAuditFixResource extends Resource
{
    use \Dashed\DashedCore\Filament\Concerns\TranslatesResourceLabels;

    protected static ?string $model = SiteAuditFix::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static string | UnitEnum | null $navigationGroup = 'SEO & site';

    protected static ?string $navigationLabel = 'Voorstellen';

    protected static ?string $modelLabel = 'voorstel';

    protected static ?string $pluralModelLabel = 'voorstellen';

    protected static ?int $navigationSort = 11;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    /** Het aantal dat nog op iemand wacht, zoals de retourenbadge dat ook doet. */
    public static function getNavigationBadge(): ?string
    {
        $aantal = SiteAuditFix::where('status', SiteAuditFix::STATUS_PENDING)->count();

        return $aantal > 0 ? (string) $aantal : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('Gevonden op'))
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
                TextColumn::make('site_id')
                    ->label(__('Site'))
                    ->formatStateUsing(fn (?string $state): string => ucfirst(Sites::get($state)['name'] ?? (string) $state))
                    ->visible(Sites::getAmountOfSites() > 1),
                TextColumn::make('check_key')
                    ->label(__('Controle'))
                    ->formatStateUsing(fn (string $state): string => app(SeoCheckRegistry::class)->check($state)?->label() ?? $state),
                TextColumn::make('page.url')
                    ->label(__('Pagina'))
                    ->limit(50)
                    ->url(fn (SiteAuditFix $record): ?string => $record->page?->url, true),
                TextColumn::make('summary')
                    ->label(__('Wat er verandert'))
                    ->wrap()
                    ->limit(120),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        SiteAuditFix::STATUS_APPLIED => 'success',
                        SiteAuditFix::STATUS_FAILED => 'danger',
                        SiteAuditFix::STATUS_STALE => 'warning',
                        SiteAuditFix::STATUS_REJECTED, SiteAuditFix::STATUS_REVERTED => 'gray',
                        default => 'info',
                    })
                    ->formatStateUsing(fn (string $state): string => self::statusLabels()[$state] ?? $state),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(self::statusLabels())
                    ->default(SiteAuditFix::STATUS_PENDING),
                SelectFilter::make('check_key')
                    ->label(__('Controle'))
                    ->options(fn (): array => SiteAuditFix::query()
                        ->select('check_key')
                        ->distinct()
                        ->pluck('check_key', 'check_key')
                        ->map(fn (string $key): string => app(SeoCheckRegistry::class)->check($key)?->label() ?? $key)
                        ->all()),
                SelectFilter::make('site_id')
                    ->label(__('Site'))
                    ->options(fn (): array => collect(Sites::getSites())->pluck('name', 'id')->all())
                    ->visible(Sites::getAmountOfSites() > 1),
            ])
            ->recordActions([
                Action::make('bekijken')
                    ->label(__('Bekijken'))
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(__('Wat er verandert'))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('Sluiten'))
                    // De samenvatting is hoogstens 120 tekens en zegt bij de
                    // linkfixers alleen wat het voornemen is. Wie een
                    // schrijfactie in gepubliceerde inhoud goedkeurt hoort de
                    // volledige oude en nieuwe waarde te kunnen zien.
                    ->modalContent(fn (SiteAuditFix $record) => view('dashed-seo::filament.modals.fix-diff', [
                        'fix' => $record,
                    ])),
                Action::make('toepassen')
                    ->label(__('Toepassen'))
                    ->icon('heroicon-o-check')
                    ->requiresConfirmation()
                    ->visible(fn (SiteAuditFix $record): bool => $record->status === SiteAuditFix::STATUS_PENDING)
                    ->action(fn (SiteAuditFix $record) => ApplySiteAuditFixJob::dispatch($record->id, auth()->id())),
                Action::make('afwijzen')
                    ->label(__('Afwijzen'))
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->visible(fn (SiteAuditFix $record): bool => $record->status === SiteAuditFix::STATUS_PENDING)
                    ->action(fn (SiteAuditFix $record) => $record->update(['status' => SiteAuditFix::STATUS_REJECTED])),
                Action::make('terugdraaien')
                    ->label(__('Terugdraaien'))
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (SiteAuditFix $record): bool => $record->status === SiteAuditFix::STATUS_APPLIED
                        && (app(SeoFixerRegistry::class)->fixerFor($record->check_key)?->supportsRevert() ?? false))
                    ->action(fn (SiteAuditFix $record) => RevertSiteAuditFixJob::dispatch($record->id, auth()->id())),
            ])
            ->toolbarActions([
                BulkAction::make('toepassenInBulk')
                    ->label(__('Toepassen'))
                    ->icon('heroicon-o-check')
                    ->requiresConfirmation()
                    ->action(fn (Collection $records) => $records
                        ->where('status', SiteAuditFix::STATUS_PENDING)
                        ->each(fn (SiteAuditFix $record) => ApplySiteAuditFixJob::dispatch($record->id, auth()->id()))),
                BulkAction::make('afwijzenInBulk')
                    ->label(__('Afwijzen'))
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->action(fn (Collection $records) => $records
                        ->where('status', SiteAuditFix::STATUS_PENDING)
                        ->each(fn (SiteAuditFix $record) => $record->update(['status' => SiteAuditFix::STATUS_REJECTED]))),
            ]);
    }

    /** @return array<string, string> */
    public static function statusLabels(): array
    {
        return [
            SiteAuditFix::STATUS_PENDING => __('Wacht op je'),
            SiteAuditFix::STATUS_APPLIED => __('Doorgevoerd'),
            SiteAuditFix::STATUS_REJECTED => __('Afgewezen'),
            SiteAuditFix::STATUS_FAILED => __('Mislukt'),
            SiteAuditFix::STATUS_REVERTED => __('Teruggedraaid'),
            SiteAuditFix::STATUS_STALE => __('Verlopen'),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSiteAuditFixes::route('/'),
        ];
    }
}
