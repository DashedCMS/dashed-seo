@php
    $scoreClass = fn ($score) => $score === null ? '' : ($score >= 90 ? 'dsp-good' : ($score >= 50 ? 'dsp-avg' : 'dsp-poor'));
    $ms = fn ($value) => $value === null ? '-' : number_format($value / 1000, 1, ',', '.') . ' s';
@endphp
<x-filament::page>
    @include('dashed-seo::filament.partials.search-performance-styles')
    @include('dashed-seo::filament.partials.site-switch')

    @if(! $enabled)
        <div class="dsp-card">
            <p class="dsp-muted">{{ __('De Prestatiemonitor staat voor deze site uit. Zet hem aan en vul een API-sleutel in bij Instellingen, Zoekprestaties.') }}</p>
        </div>
    @elseif(! $urls)
        <div class="dsp-card">
            <p class="dsp-muted">{{ __('Er zijn nog geen metingen. Dat gebeurt elke nacht, of nu met de knop Nu meten.') }}</p>
        </div>
    @endif

    @foreach($urls as $url => $data)
        <div class="dsp-card">
            <h2 class="dsp-break">{{ $url }}</h2>
            <div class="dsp-grid">
                @foreach(['mobile' => __('Mobiel'), 'desktop' => __('Desktop')] as $strategy => $label)
                    @php($m = $data['latest'][$strategy])
                    <div>
                        <div class="dsp-muted">{{ $label }}</div>
                        @if(! $m)
                            <div class="dsp-muted">-</div>
                        @elseif($m->error)
                            <div class="dsp-poor">{{ __('Meting mislukt: :fout', ['fout' => $m->error]) }}</div>
                        @else
                            <div class="dsp-score {{ $scoreClass($m->performance_score) }}">{{ $m->performance_score ?? '-' }}</div>
                            <div class="dsp-muted">
                                {{ __('Lab: LCP :lcp, CLS :cls, TBT :tbt ms', ['lcp' => $ms($m->lab_lcp_ms), 'cls' => $m->lab_cls ?? '-', 'tbt' => $m->lab_tbt_ms ?? '-']) }}
                            </div>
                            <div class="dsp-muted">
                                @if($m->field_lcp_ms !== null || $m->field_inp_ms !== null)
                                    {{ __('Echte bezoekers: LCP :lcp, INP :inp ms, CLS :cls', ['lcp' => $ms($m->field_lcp_ms), 'inp' => $m->field_inp_ms ?? '-', 'cls' => $m->field_cls ?? '-']) }}
                                @else
                                    {{ __('Te weinig verkeer voor velddata van echte bezoekers.') }}
                                @endif
                            </div>
                            <div class="dsp-muted">{{ $m->measured_at->format('d-m-Y H:i') }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
            <svg class="dsp-chart" viewBox="0 0 600 120" preserveAspectRatio="none" role="img" aria-label="{{ __('Prestatiescore van de laatste :dagen dagen', ['dagen' => \Dashed\DashedSeo\Filament\Pages\PageSpeedMonitorPage::DAYS]) }}">
                <line x1="0" y1="12" x2="600" y2="12" stroke="rgba(22,163,74,.35)" stroke-dasharray="4 4" />
                <line x1="0" y1="60" x2="600" y2="60" stroke="rgba(217,119,6,.35)" stroke-dasharray="4 4" />
                @if($data['series']['mobile'])
                    <polyline points="{{ $data['series']['mobile'] }}" fill="none" stroke="#2563eb" stroke-width="2" vector-effect="non-scaling-stroke" />
                @endif
                @if($data['series']['desktop'])
                    <polyline points="{{ $data['series']['desktop'] }}" fill="none" stroke="#9333ea" stroke-width="2" vector-effect="non-scaling-stroke" />
                @endif
            </svg>
            <div class="dsp-legend">
                <span><span class="dsp-dot" style="background:#2563eb"></span>{{ __('Mobiel') }}</span>
                <span><span class="dsp-dot" style="background:#9333ea"></span>{{ __('Desktop') }}</span>
                <span class="dsp-muted">{{ __('Stippellijnen: score 90 en 50') }}</span>
            </div>
        </div>
    @endforeach
</x-filament::page>
