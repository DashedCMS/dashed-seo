<x-filament::page>
    @include('dashed-seo::filament.partials.search-performance-styles')
    @include('dashed-seo::filament.partials.site-switch')

    @if(! $ready)
        <div class="dsp-card">
            <p class="dsp-muted">{{ __('Search Console staat voor deze site uit of is nog niet gekoppeld. Zet het aan en koppel het bij Instellingen, Zoekprestaties.') }}</p>
        </div>
    @endif

    @if($latest)
        <p class="dsp-muted">{{ __('Cijfers tot en met :datum. Search Console loopt een paar dagen achter.', ['datum' => $latest->format('d-m-Y')]) }}</p>
    @elseif($ready)
        <p class="dsp-muted">{{ __('Er zijn nog geen cijfers opgehaald. Dat gebeurt elke nacht, of nu met de knop Nu ophalen.') }}</p>
    @endif

    <div class="dsp-card">
        <h2>{{ __('Dalers') }}</h2>
        <p class="dsp-muted">{{ __('Zoektermen waarvan de gemiddelde positie in de laatste week minstens :aantal plaatsen slechter is dan in de vier weken daarvoor.', ['aantal' => $threshold]) }}</p>
        @if($decliners->isEmpty())
            <p class="dsp-muted">{{ __('Geen dalers gevonden.') }}</p>
        @else
            @include('dashed-seo::filament.partials.keyword-table', ['rows' => $decliners, 'showPage' => true])
        @endif
    </div>

    <div class="dsp-card">
        <h2>{{ __('Beste zoektermen van de laatste 28 dagen') }}</h2>
        @if($topQueries->isEmpty())
            <p class="dsp-muted">{{ __('Nog geen gegevens.') }}</p>
        @else
            @include('dashed-seo::filament.partials.keyword-table', ['rows' => $topQueries])
        @endif
    </div>
</x-filament::page>
