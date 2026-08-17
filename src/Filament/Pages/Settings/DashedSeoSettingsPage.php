<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Filament\Pages\Settings;

use UnitEnum;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Dashed\DashedCore\Classes\Sites;
use Filament\Schemas\Components\Tabs;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Fieldset;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedSeo\Settings\AuditSettings;
use Dashed\DashedCore\Traits\HasSettingsPermission;

class DashedSeoSettingsPage extends Page
{
    use HasSettingsPermission;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-magnifying-glass-circle';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationLabel = 'Sitescan instellingen';

    protected static string | UnitEnum | null $navigationGroup = 'SEO & site';

    protected static ?string $title = 'Sitescan instellingen';

    protected string $view = 'dashed-core::settings.pages.default-settings';

    public array $data = [];

    public function mount(): void
    {
        $formData = [];

        foreach (Sites::getSites() as $site) {
            $settings = AuditSettings::forSite($site['id']);

            $formData["site_audit_base_url_{$site['id']}"] = $settings->baseUrl;
            $formData["site_audit_max_pages_{$site['id']}"] = $settings->maxPages;
            $formData["site_audit_max_depth_{$site['id']}"] = $settings->maxDepth;
            $formData["site_audit_delay_ms_{$site['id']}"] = $settings->delayMs;
            $formData["site_audit_weekly_{$site['id']}"] = $settings->weekly;
            $formData["site_audit_stale_after_minutes_{$site['id']}"] = $settings->staleAfterMinutes;

            foreach (AuditSettings::THRESHOLD_KEYS as $key) {
                $formData["site_audit_threshold_{$key}_{$site['id']}"] = $settings->thresholds[$key];
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
                    TextInput::make("site_audit_base_url_{$id}")
                        ->label(__('Basis-URL'))
                        ->helperText(__('Het adres waar de scan begint. Een site kent in het CMS geen eigen adres, dus dit moet je hier opgeven.'))
                        ->url()
                        ->required()
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
                    Toggle::make("site_audit_weekly_{$id}")
                        ->label(__('Elke week automatisch scannen'))
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
                ])
                ->columns(2);
        }

        return $schema->schema([Tabs::make('Sites')->tabs($tabs)])->statePath('data');
    }

    public function submit(): void
    {
        $formState = $this->form->getState();

        foreach (Sites::getSites() as $site) {
            $id = $site['id'];

            foreach (['base_url', 'max_pages', 'max_depth', 'delay_ms', 'weekly', 'stale_after_minutes'] as $name) {
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
        }

        $this->form->fill($formState);

        Notification::make()->title(__('De sitescan-instellingen zijn opgeslagen'))->success()->send();
    }
}
