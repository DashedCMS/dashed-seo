<div>
    @include('dashed-seo::filament.partials.search-performance-styles')

    @if(! $latest)
        <p class="dsp-muted">{{ __('Er zijn nog geen cijfers uit Search Console opgehaald.') }}</p>
    @elseif($rows->isEmpty())
        <p class="dsp-muted">{{ __('Search Console heeft in de laatste 28 dagen geen zoektermen voor deze pagina (:paden).', ['paden' => implode(', ', $paths)]) }}</p>
    @else
        @php
            $max = max(1, max($daily ?: [0]));
            $count = max(1, count($daily) - 1);
            $points = collect(array_values($daily))->map(fn ($clicks, $i) => round($i / $count * 600, 1) . ',' . round(60 - $clicks / $max * 56, 1))->implode(' ');
        @endphp
        <p class="dsp-muted">{{ __('Klikken per dag, laatste 28 dagen tot en met :datum (:totaal in totaal)', ['datum' => $latest->format('d-m-Y'), 'totaal' => array_sum($daily)]) }}</p>
        <svg class="dsp-chart" style="height:60px" viewBox="0 0 600 60" preserveAspectRatio="none" role="img" aria-label="{{ __('Klikken per dag') }}">
            <polyline points="{{ $points }}" fill="none" stroke="#2563eb" stroke-width="2" vector-effect="non-scaling-stroke" />
        </svg>
        @include('dashed-seo::filament.partials.keyword-table', ['rows' => $rows])
    @endif
</div>
