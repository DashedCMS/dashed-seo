<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Filament\Pages\Settings;

use UnitEnum;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Dashed\DashedCore\Classes\Sites;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Dashed\DashedSeo\SeoCheckRegistry;
use Dashed\DashedSeo\SeoFixerRegistry;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Tabs\Tab;
use Dashed\DashedSeo\Settings\FixSettings;
use Filament\Forms\Components\Placeholder;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedSeo\Settings\AuditSettings;
use Dashed\DashedSeo\Filament\Support\ReportData;
use Dashed\DashedCore\Traits\HasSettingsPermission;
use Dashed\DashedSeo\Filament\Actions\ScanNowAction;

class DashedSeoSettingsPage extends Page
{
    use \Dashed\DashedCore\Filament\Concerns\TranslatesPageLabels;
    use HasSettingsPermission;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-magnifying-glass-circle';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationLabel = 'Sitescan instellingen';

    protected static string | UnitEnum | null $navigationGroup = 'SEO & site';

    protected static ?string $title = 'Sitescan instellingen';

    protected string $view = 'dashed-core::settings.pages.default-settings';

    public array $data = [];

    /**
     * Dezelfde knop als op het sitescans-overzicht. Wie hier de limieten of
     * drempelwaarden aanpast wil meteen zien wat dat oplevert, zonder eerst
     * een ander scherm op te moeten zoeken.
     */
    protected function getHeaderActions(): array
    {
        return [
            ScanNowAction::make(),
        ];
    }

    public function mount(): void
    {
        $formData = [];

        foreach (Sites::getSites() as $site) {
            $settings = AuditSettings::forSite($site['id']);

            $formData["site_audit_max_pages_{$site['id']}"] = $settings->maxPages;
            $formData["site_audit_max_depth_{$site['id']}"] = $settings->maxDepth;
            $formData["site_audit_delay_ms_{$site['id']}"] = $settings->delayMs;
            $formData["site_audit_frequency_{$site['id']}"] = $settings->frequency;
            $formData["site_audit_stale_after_minutes_{$site['id']}"] = $settings->staleAfterMinutes;

            foreach (AuditSettings::THRESHOLD_KEYS as $key) {
                $formData["site_audit_threshold_{$key}_{$site['id']}"] = $settings->thresholds[$key];
            }

            $formData["site_audit_fixes_enabled_{$site['id']}"] = FixSettings::enabled($site['id']);
            $formData["site_audit_fix_ai_limit_{$site['id']}"] = FixSettings::aiLimit($site['id']);

            foreach (app(SeoFixerRegistry::class)->checkKeys() as $checkKey) {
                $formData["site_audit_fix_mode_{$checkKey}_{$site['id']}"] = FixSettings::modeFor($checkKey, $site['id']);
            }
        }

        $this->form->fill($formData);
    }

    public function form(Schema $schema): Schema
    {
        $tabs = [];

        foreach (Sites::getSites() as $site) {
            $id = $site['id'];

            $tabs[] = Tab::make($id)
                ->label(ucfirst($site['name']))
                ->schema([
                    Placeholder::make("site_audit_base_url_{$id}")
                        ->label(__('Adres van de website'))
                        ->helperText(__('Hier begint de scan. Dit is het adres van de website zelf en is daarom niet apart in te stellen.'))
                        ->content(AuditSettings::forSite($id)->baseUrl)
                        ->columnSpanFull(),
                    TextInput::make("site_audit_max_pages_{$id}")
                        ->label(__('Maximaal aantal pagina\'s'))
                        ->helperText(__('De scan stopt met ontdekken zodra dit aantal is bereikt. Leeg of 0 betekent geen limiet, dus de hele site.'))
                        ->numeric()
                        ->minValue(0),
                    TextInput::make("site_audit_max_depth_{$id}")
                        ->label(__('Maximale diepte'))
                        ->helperText(__('Hoeveel klikken vanaf de startpagina de scan doorloopt. Leeg of 0 betekent geen grens.'))
                        ->numeric()
                        ->minValue(0),
                    TextInput::make("site_audit_delay_ms_{$id}")
                        ->label(__('Vertraging tussen verzoeken in milliseconden'))
                        ->helperText(__('Hoger zetten als de site het zwaar krijgt van de scan.'))
                        ->numeric()
                        ->minValue(0)
                        ->required(),
                    TextInput::make("site_audit_stale_after_minutes_{$id}")
                        ->label(__('Vastgelopen na hoeveel minuten'))
                        ->helperText(__('Een scan die zo lang geen teken van leven geeft wordt afgebroken, zodat de site niet op slot blijft.'))
                        ->numeric()
                        ->minValue(1)
                        ->required(),
                    Select::make("site_audit_frequency_{$id}")
                        ->label(__('Automatisch scannen'))
                        ->helperText(__('Hoe vaak de scan vanzelf draait. Je krijgt na elke scan een melding in de app.'))
                        ->options([
                            AuditSettings::FREQUENCY_NEVER => __('Nooit'),
                            AuditSettings::FREQUENCY_DAILY => __('Elke dag'),
                            AuditSettings::FREQUENCY_WEEKLY => __('Elke week'),
                            AuditSettings::FREQUENCY_MONTHLY => __('Elke maand'),
                        ])
                        ->selectablePlaceholder(false)
                        ->required()
                        ->columnSpanFull(),
                    Fieldset::make(__('Drempelwaarden'))
                        ->schema([
                            TextInput::make("site_audit_threshold_title_max_{$id}")
                                ->label(__('Titel maximaal aantal tekens'))->numeric()->required(),
                            TextInput::make("site_audit_threshold_title_min_{$id}")
                                ->label(__('Titel minimaal aantal tekens'))->numeric()->required(),
                            TextInput::make("site_audit_threshold_description_max_{$id}")
                                ->label(__('Omschrijving maximaal aantal tekens'))->numeric()->required(),
                            TextInput::make("site_audit_threshold_min_words_{$id}")
                                ->label(__('Minimaal aantal woorden per pagina'))->numeric()->required(),
                            TextInput::make("site_audit_threshold_slow_response_ms_{$id}")
                                ->label(__('Trage reactietijd vanaf milliseconden'))->numeric()->required(),
                            TextInput::make("site_audit_threshold_html_max_bytes_{$id}")
                                ->label(__('HTML te groot vanaf bytes'))->numeric()->required(),
                            TextInput::make("site_audit_threshold_css_max_bytes_{$id}")
                                ->label(__('CSS te groot vanaf bytes'))->numeric()->required(),
                            TextInput::make("site_audit_threshold_js_max_bytes_{$id}")
                                ->label(__('JavaScript te groot vanaf bytes'))->numeric()->required(),
                            TextInput::make("site_audit_threshold_max_redirect_hops_{$id}")
                                ->label(__('Maximaal aantal doorverwijzingen achter elkaar'))->numeric()->required(),
                        ])
                        ->columns(3)
                        ->columnSpanFull(),
                    Section::make(__('Automatisch oplossen'))
                        ->description(__('Wat de scan zelf mag rechtzetten en wat er eerst langs jou komt. Staat standaard uit.'))
                        ->collapsed()
                        ->schema(array_merge([
                            Toggle::make("site_audit_fixes_enabled_{$id}")
                                ->label(__('Automatisch oplossen aanzetten'))
                                ->helperText(__('Zolang dit uit staat verandert een scan niets aan de site. Let op: fixen die vanzelf draaien doen dat zonder gebruiker, en slaan dus de bewerkrechten per pagina over.')),
                            TextInput::make("site_audit_fix_ai_limit_{$id}")
                                ->label(__('Maximaal aantal AI-voorstellen per scan'))
                                ->helperText(__('Voorkomt dat de eerste scan van een grote site in een keer honderden AI-aanroepen kost. Wat erboven valt blijft gewoon als bevinding staan.'))
                                ->numeric()
                                ->minValue(0)
                                ->default(FixSettings::DEFAULT_AI_LIMIT),
                        ], $this->fixModeFields($id)))
                        ->columns(1)
                        ->columnSpanFull(),
                ])
                ->columns(2);
        }

        return $schema->schema([Tabs::make('Sites')->tabs($tabs)])->statePath('data');
    }

    /**
     * Een keuzelijst per controle die een fixer heeft, in de volgorde van het
     * rapport. Controles zonder fixer komen hier niet voor: daar valt niets te
     * kiezen.
     *
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    protected function fixModeFields(string $siteId): array
    {
        $checks = app(SeoCheckRegistry::class);
        $fixers = app(SeoFixerRegistry::class);

        $perSectie = [];

        foreach ($fixers->checkKeys() as $checkKey) {
            $check = $checks->check($checkKey);
            $perSectie[$check?->section() ?? 'overig'][$checkKey] = $check?->label() ?? $checkKey;
        }

        $velden = [];

        foreach (ReportData::SECTIONS as $sectie => $label) {
            if (! isset($perSectie[$sectie])) {
                continue;
            }

            $velden[] = Fieldset::make(__($label))
                ->schema(array_map(
                    fn (string $checkKey): Select => Select::make("site_audit_fix_mode_{$checkKey}_{$siteId}")
                        ->label($perSectie[$sectie][$checkKey])
                        ->options([
                            FixSettings::MODE_OFF => __('Uit'),
                            FixSettings::MODE_SUGGESTION => __('Voorstel'),
                            FixSettings::MODE_AUTO => __('Automatisch'),
                        ])
                        ->default(FixSettings::modeFor($checkKey, $siteId)),
                    array_keys($perSectie[$sectie]),
                ))
                ->columns(2);
        }

        return $velden;
    }

    public function submit(): void
    {
        $formState = $this->form->getState();

        foreach (Sites::getSites() as $site) {
            $id = $site['id'];

            foreach (['max_pages', 'max_depth', 'delay_ms', 'frequency', 'stale_after_minutes'] as $name) {
                $value = $formState["site_audit_{$name}_{$id}"] ?? null;

                // Een leeg limietveld wordt vastgelegd als 0, oftewel geen
                // limiet. Zou het als lege waarde opgeslagen worden, dan valt
                // AuditSettings terug op de standaard en scant de site alsnog
                // maar tot een grens die de beheerder net heeft weggehaald.
                if (in_array($name, ['max_pages', 'max_depth'], true) && ($value === null || $value === '')) {
                    $value = 0;
                }

                Customsetting::set("site_audit_{$name}", $value, $id);
            }

            foreach (AuditSettings::THRESHOLD_KEYS as $key) {
                Customsetting::set(
                    "site_audit_threshold_{$key}",
                    $formState["site_audit_threshold_{$key}_{$id}"] ?? null,
                    $id
                );
            }

            Customsetting::set('site_audit_fixes_enabled', $formState["site_audit_fixes_enabled_{$id}"] ?? false, $id);
            Customsetting::set('site_audit_fix_ai_limit', $formState["site_audit_fix_ai_limit_{$id}"] ?? FixSettings::DEFAULT_AI_LIMIT, $id);

            foreach (app(SeoFixerRegistry::class)->checkKeys() as $checkKey) {
                $waarde = $formState["site_audit_fix_mode_{$checkKey}_{$id}"] ?? null;

                if (in_array($waarde, FixSettings::modes(), true)) {
                    Customsetting::set("site_audit_fix_mode_{$checkKey}", $waarde, $id);
                }
            }
        }

        $this->form->fill($formState);

        Notification::make()->title(__('De sitescan-instellingen zijn opgeslagen'))->success()->send();
    }
}
