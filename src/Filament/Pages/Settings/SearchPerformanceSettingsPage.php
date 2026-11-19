<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Filament\Pages\Settings;

use UnitEnum;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Cache;
use Dashed\DashedCore\Classes\Sites;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Forms\Components\Placeholder;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedSeo\PageSpeed\PageSpeedUrls;
use Dashed\DashedCore\Classes\GoogleOAuthFlows;
use Dashed\DashedSeo\Settings\PageSpeedSettings;
use Dashed\DashedCore\Traits\HasSettingsPermission;
use Dashed\DashedSeo\SearchConsole\SearchConsoleClient;
use Dashed\DashedSeo\Settings\SearchConsoleSettings;

/**
 * Instellingen voor Search Console en de Prestatiemonitor (PageSpeed
 * Insights), per site. Beide staan standaard uit.
 */
class SearchPerformanceSettingsPage extends Page
{
    use \Dashed\DashedCore\Filament\Concerns\TranslatesPageLabels;
    use HasSettingsPermission;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-chart-bar';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationLabel = 'Zoekprestaties instellingen';

    protected static string | UnitEnum | null $navigationGroup = 'SEO & site';

    protected static ?string $title = 'Zoekprestaties instellingen';

    protected string $view = 'dashed-core::settings.pages.default-settings';

    public array $data = [];

    public function mount(): void
    {
        $formData = [];

        foreach (Sites::getSites() as $site) {
            $id = $site['id'];

            $formData["search_console_enabled_{$id}"] = SearchConsoleSettings::enabled($id);
            $formData["search_console_property_{$id}"] = SearchConsoleSettings::property($id);
            $formData["search_console_drop_threshold_{$id}"] = SearchConsoleSettings::dropThreshold($id);
            $formData["search_console_min_impressions_{$id}"] = SearchConsoleSettings::minImpressions($id);
            $formData["search_console_alerts_enabled_{$id}"] = SearchConsoleSettings::alertsEnabled($id);

            $formData["pagespeed_enabled_{$id}"] = PageSpeedSettings::enabled($id);
            $formData["pagespeed_api_key_{$id}"] = PageSpeedSettings::apiKey($id);
            $formData["pagespeed_auto_urls_{$id}"] = PageSpeedSettings::autoUrls($id);
            $formData["pagespeed_urls_{$id}"] = implode("\n", PageSpeedSettings::extraUrls($id));
            $formData["pagespeed_alert_drop_{$id}"] = PageSpeedSettings::alertDrop($id);
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
                    $this->searchConsoleSection($id),
                    $this->pageSpeedSection($id),
                ]);
        }

        return $schema->schema([Tabs::make('Sites')->tabs($tabs)])->statePath('data');
    }

    protected function searchConsoleSection(string $id): Section
    {
        $properties = $this->properties($id);

        return Section::make(__('Google Search Console'))
            ->description(__('Haalt elke dag per pagina de zoektermen op waarop de site gevonden wordt, met klikken, vertoningen, CTR en positie. Staat standaard uit.'))
            ->schema([
                Toggle::make("search_console_enabled_{$id}")
                    ->label(__('Search Console ophalen'))
                    ->columnSpanFull(),
                Placeholder::make("search_console_status_{$id}")
                    ->label(__('Koppeling'))
                    ->content(fn () => match (true) {
                        ! SearchConsoleSettings::clientConfigured() => __('Vul eerst de Google client-id en het client-secret in bij Reviews, Google-instellingen. Search Console gebruikt dezelfde Google-app.'),
                        ! class_exists(GoogleOAuthFlows::class) => __('Werk dashed-core bij om Search Console te kunnen koppelen.'),
                        SearchConsoleSettings::connected($id) => __('Gekoppeld met Google.'),
                        default => __('Nog niet gekoppeld.'),
                    })
                    ->columnSpanFull(),
                Actions::make([
                    Action::make("connect_search_console_{$id}")
                        ->label(fn () => SearchConsoleSettings::connected($id) ? __('Opnieuw koppelen met Google') : __('Koppelen met Google'))
                        ->icon('heroicon-o-link')
                        ->visible(fn () => SearchConsoleSettings::clientConfigured() && class_exists(GoogleOAuthFlows::class))
                        ->url(fn () => route('google.oauth.redirect', ['flow' => SearchConsoleSettings::OAUTH_FLOW, 'site' => $id])),
                    Action::make("disconnect_search_console_{$id}")
                        ->label(__('Koppeling verbreken'))
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn () => SearchConsoleSettings::connected($id))
                        ->action(function () use ($id): void {
                            SearchConsoleSettings::forgetRefreshToken($id);
                            Notification::make()->title(__('De koppeling met Search Console is verbroken'))->success()->send();
                        }),
                ])->columnSpanFull(),
                $properties
                    ? Select::make("search_console_property_{$id}")
                        ->label(__('Property'))
                        ->options(array_combine($properties, $properties))
                        ->searchable()
                        ->helperText(__('De property in Search Console die bij deze site hoort.'))
                        ->columnSpanFull()
                    : TextInput::make("search_console_property_{$id}")
                        ->label(__('Property'))
                        ->placeholder('sc-domain:voorbeeld.nl')
                        ->helperText(__('Precies zoals in Search Console: sc-domain:voorbeeld.nl voor een domeinproperty, of https://www.voorbeeld.nl/ voor een URL-property. Na het koppelen verschijnt hier een keuzelijst.'))
                        ->columnSpanFull(),
                TextInput::make("search_console_drop_threshold_{$id}")
                    ->label(__('Daler vanaf (plaatsen)'))
                    ->helperText(__('Een zoekterm is een daler als de gemiddelde positie in de laatste week zoveel plaatsen slechter is dan in de vier weken daarvoor.'))
                    ->numeric()
                    ->minValue(0.5)
                    ->step(0.5),
                TextInput::make("search_console_min_impressions_{$id}")
                    ->label(__('Minimaal aantal vertoningen in de laatste week'))
                    ->helperText(__('Zoektermen met minder vertoningen schommelen te veel om iets over te zeggen.'))
                    ->numeric()
                    ->minValue(0),
                Toggle::make("search_console_alerts_enabled_{$id}")
                    ->label(__('Melding sturen bij een duidelijke daling'))
                    ->helperText(__('Een belletje en een mail aan de beheerders. Dezelfde zoekterm op dezelfde pagina wordt hooguit eens per twee weken gemeld.'))
                    ->columnSpanFull(),
            ])
            ->columns(2)
            ->columnSpanFull();
    }

    protected function pageSpeedSection(string $id): Section
    {
        return Section::make(__('Prestatiemonitor (PageSpeed Insights)'))
            ->description(__('Meet elke dag de laadsnelheid van een paar vaste pagina\'s, mobiel en desktop, met de Core Web Vitals van echte bezoekers als Google die heeft. Staat standaard uit.'))
            ->schema([
                Toggle::make("pagespeed_enabled_{$id}")
                    ->label(__('Prestatiemonitor aanzetten'))
                    ->columnSpanFull(),
                TextInput::make("pagespeed_api_key_{$id}")
                    ->label(__('API-sleutel van PageSpeed Insights'))
                    ->helperText(__('Een API-sleutel uit Google Cloud met de PageSpeed Insights API aan. Zonder sleutel deelt de site de quotum van iedereen en mislukken metingen.'))
                    ->password()
                    ->revealable()
                    ->columnSpanFull(),
                Toggle::make("pagespeed_auto_urls_{$id}")
                    ->label(__('Homepage, een product en een categorie vanzelf meten'))
                    ->columnSpanFull(),
                Textarea::make("pagespeed_urls_{$id}")
                    ->label(__('Extra URL\'s om te meten'))
                    ->helperText(__('Eén volledige URL per regel. In totaal hooguit :aantal URL\'s per site.', ['aantal' => PageSpeedSettings::MAX_URLS]))
                    ->rows(4)
                    ->columnSpanFull(),
                Placeholder::make("pagespeed_current_urls_{$id}")
                    ->label(__('Wordt nu gemeten'))
                    ->content(fn () => implode(', ', PageSpeedUrls::forSite($id)) ?: '-')
                    ->columnSpanFull(),
                TextInput::make("pagespeed_alert_drop_{$id}")
                    ->label(__('Melding bij een daling van (punten)'))
                    ->helperText(__('Een melding als de prestatiescore zoveel punten lager is dan het gemiddelde van de laatste zeven metingen van die URL.'))
                    ->numeric()
                    ->minValue(1),
            ])
            ->columns(2)
            ->columnSpanFull();
    }

    /** @return array<int, string> */
    protected function properties(string $id): array
    {
        if (! SearchConsoleSettings::connected($id) || ! SearchConsoleSettings::clientConfigured()) {
            return [];
        }

        $properties = Cache::remember(
            'dashed-seo:search-console-properties:' . $id,
            600,
            fn () => rescue(fn () => (new SearchConsoleClient($id))->properties(), [], report: false),
        );

        $current = SearchConsoleSettings::property($id);

        if ($properties && $current && ! in_array($current, $properties, true)) {
            $properties[] = $current;
        }

        return $properties;
    }

    public function submit(): void
    {
        $formState = $this->form->getState();

        foreach (Sites::getSites() as $site) {
            $id = $site['id'];

            Customsetting::set('search_console_enabled', (bool) ($formState["search_console_enabled_{$id}"] ?? false), $id);
            Customsetting::set('search_console_property', trim((string) ($formState["search_console_property_{$id}"] ?? '')), $id);
            Customsetting::set('search_console_drop_threshold', (string) ($formState["search_console_drop_threshold_{$id}"] ?? SearchConsoleSettings::DEFAULT_DROP_THRESHOLD), $id);
            Customsetting::set('search_console_min_impressions', (string) ($formState["search_console_min_impressions_{$id}"] ?? SearchConsoleSettings::DEFAULT_MIN_IMPRESSIONS), $id);
            Customsetting::set('search_console_alerts_enabled', (bool) ($formState["search_console_alerts_enabled_{$id}"] ?? false), $id);

            Customsetting::set('pagespeed_enabled', (bool) ($formState["pagespeed_enabled_{$id}"] ?? false), $id);
            Customsetting::set('pagespeed_api_key', trim((string) ($formState["pagespeed_api_key_{$id}"] ?? '')), $id);
            Customsetting::set('pagespeed_auto_urls', (bool) ($formState["pagespeed_auto_urls_{$id}"] ?? false), $id);
            Customsetting::set('pagespeed_urls', (string) ($formState["pagespeed_urls_{$id}"] ?? ''), $id);
            Customsetting::set('pagespeed_alert_drop', (string) ($formState["pagespeed_alert_drop_{$id}"] ?? PageSpeedSettings::DEFAULT_ALERT_DROP), $id);
        }

        $this->form->fill($formState);

        Notification::make()->title(__('De instellingen voor zoekprestaties zijn opgeslagen'))->success()->send();
    }
}
