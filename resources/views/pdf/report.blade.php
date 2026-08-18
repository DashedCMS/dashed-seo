{{--
    De uitdraai van het sitescan-rapport. Bewust losse opmaak en geen enkele
    klasse uit het beheerscherm: dompdf kent Tailwind niet en zou daar een
    kale, ongeordende pagina van maken.
--}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ __('Sitescan van :datum', ['datum' => $audit->created_at?->format('d-m-Y H:i')]) }}</title>

    <style>
        @page {
            margin: 90px 40px 60px 40px;
        }

        body {
            font-family: 'Helvetica', sans-serif;
            font-size: 11px;
            color: #1f2937;
            margin: 0;
        }

        header {
            position: fixed;
            top: -60px;
            left: 0;
            right: 0;
            height: 40px;
            border-bottom: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 10px;
        }

        header td:last-child {
            text-align: right;
        }

        footer {
            position: fixed;
            bottom: -40px;
            left: 0;
            right: 0;
            color: #9ca3af;
            font-size: 10px;
            text-align: right;
        }

        .page-number:after {
            content: counter(page);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        h1 {
            font-size: 22px;
            margin: 0 0 4px 0;
        }

        h2 {
            font-size: 16px;
            margin: 0 0 10px 0;
            padding-bottom: 4px;
            border-bottom: 2px solid #111827;
        }

        h3 {
            font-size: 12px;
            margin: 0;
        }

        .lead {
            color: #6b7280;
            margin: 0 0 18px 0;
        }

        .warning {
            border: 1px solid #fcd34d;
            background: #fffbeb;
            color: #92400e;
            padding: 8px 10px;
            margin-bottom: 8px;
        }

        .cards td {
            width: 25%;
            border: 1px solid #e5e7eb;
            padding: 10px;
            vertical-align: top;
        }

        .card-label {
            color: #6b7280;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .card-value {
            font-size: 26px;
            font-weight: bold;
            color: #111827;
            padding-top: 2px;
        }

        .up {
            color: #15803d;
        }

        .down {
            color: #b91c1c;
        }

        .muted {
            color: #6b7280;
        }

        .rows td {
            /* De rand komt van .cards td en zou hier de losse regeltjes in
               hokjes zetten. */
            border: 0;
            padding: 1px 0;
        }

        .rows td:last-child {
            text-align: right;
            font-weight: bold;
        }

        .section {
            page-break-before: always;
        }

        .check {
            border: 1px solid #e5e7eb;
            margin-bottom: 14px;
        }

        .check th {
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
            padding: 8px 10px;
            text-align: left;
            font-weight: normal;
        }

        .check-label {
            font-size: 12px;
            font-weight: bold;
        }

        .check th.check-counts {
            /* Zonder breedte kruipt de kolom naar het midden: de tabel deelt
               de ruimte dan naar de inhoud in plaats van naar de bladspiegel. */
            width: 35%;
            text-align: right;
            white-space: nowrap;
        }

        .badge {
            display: inline-block;
            padding: 1px 5px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .badge-error {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-notice {
            background: #f3f4f6;
            color: #4b5563;
        }

        /* De uitleg hoort bij de kop erboven en mag daar niet van losgescheurd
           worden. De lijst adressen eronder loopt wel gewoon door: die is bij
           een grote controle langer dan een bladzijde. */
        .explain {
            page-break-inside: avoid;
        }

        .explain > td {
            padding: 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .explain table td {
            width: 50%;
            padding: 8px 10px;
            vertical-align: top;
        }

        .explain-label {
            color: #6b7280;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding-bottom: 2px;
        }

        .finding td {
            padding: 4px 10px;
            border-bottom: 1px solid #f3f4f6;
            /* Adressen zijn lang en hebben geen spaties: zonder dit loopt de
               tabel buiten de bladspiegel. */
            word-wrap: break-word;
            word-break: break-all;
        }

        .finding:last-child td {
            border-bottom: 0;
        }

        .context {
            color: #6b7280;
            font-size: 9px;
        }

        .new {
            color: #b91c1c;
            font-weight: bold;
        }
    </style>
</head>
<body>
<header>
    <table>
        <tr>
            <td>{{ $audit->base_url }}</td>
            <td>{{ __('Sitescan van :datum', ['datum' => $audit->created_at?->format('d-m-Y H:i')]) }}</td>
        </tr>
    </table>
</header>

<footer>
    {{ __('Pagina') }} <span class="page-number"></span>
</footer>

@php
    $cover = $report['cover'];
    $severityLabels = [
        'error' => __('Fout'),
        'warning' => __('Waarschuwing'),
        'notice' => __('Notitie'),
    ];
@endphp

<h1>{{ __('Sitescan') }}</h1>
<p class="lead">
    {{ $audit->base_url }} &middot;
    {{ __('gescand op :datum', ['datum' => ($audit->finished_at ?? $audit->created_at)?->format('d-m-Y H:i')]) }}
</p>

@foreach ($cover['warnings_list'] as $warning)
    <div class="warning">{{ $warning }}</div>
@endforeach

<table class="cards">
    <tr>
        <td>
            <div class="card-label">{{ __('Technische score') }}</div>
            <div class="card-value">{{ $cover['score'] ?? '-' }}</div>
            @if ($cover['delta'] !== null)
                <div @class(['up' => $cover['delta'] > 0, 'down' => $cover['delta'] < 0, 'muted' => $cover['delta'] === 0])>
                    {{ $cover['delta'] > 0 ? '+' : '' }}{{ $cover['delta'] }}
                    {{ __('ten opzichte van de scan van :datum', ['datum' => $cover['previous_finished_at']?->format('d-m-Y')]) }}
                </div>
            @else
                <div class="muted">{{ __('Eerste scan van deze site') }}</div>
            @endif
        </td>
        <td>
            <div class="card-label">{{ __('Gevonden problemen') }}</div>
            <table class="rows">
                <tr><td>{{ __('Fouten') }}</td><td>{{ $cover['errors'] }}</td></tr>
                <tr><td>{{ __('Waarschuwingen') }}</td><td>{{ $cover['warnings'] }}</td></tr>
                <tr><td>{{ __('Notities') }}</td><td>{{ $cover['notices'] }}</td></tr>
            </table>
        </td>
        <td>
            <div class="card-label">{{ __('Opgehaalde pagina\'s') }}</div>
            <div class="card-value">{{ $cover['pages_crawled'] }}</div>
            <div class="muted">
                {{ __(':aantal adressen gevonden', ['aantal' => $cover['urls_found']]) }}@if ($cover['pages_blocked'] > 0), {{ __(':aantal afgeschermd', ['aantal' => $cover['pages_blocked']]) }}@endif
            </div>
        </td>
        <td>
            <div class="card-label">{{ __('Grootste problemen') }}</div>
            <table class="rows">
                @forelse ($cover['top'] as $item)
                    <tr><td>{{ $item['label'] }}</td><td>{{ $item['count'] }}</td></tr>
                @empty
                    <tr><td class="muted">{{ __('Niets gevonden') }}</td><td></td></tr>
                @endforelse
            </table>
        </td>
    </tr>
</table>

@forelse ($report['sections'] as $section)
    <div class="section">
        <h2>{{ $section['label'] }}
            <span class="muted" style="font-weight: normal; font-size: 11px;">
                {{ trans_choice('{1}:aantal probleem|[2,*]:aantal problemen', $section['total'], ['aantal' => $section['total']]) }}
            </span>
        </h2>

        @foreach ($section['checks'] as $check)
            {{-- Eén tabel per controle, met de kop in de thead: dompdf herhaalt
                 die boven aan elke bladzijde waarover de lijst doorloopt, dus
                 een bladzijde vol adressen zegt altijd waar hij bij hoort. --}}
            <table class="check">
                <thead>
                    <tr>
                        <th>
                            <span class="badge badge-{{ $check['severity'] }}">{{ $severityLabels[$check['severity']] ?? $check['severity'] }}</span>
                            <span class="check-label">{{ $check['label'] }}</span>
                        </th>
                        <th class="check-counts">
                            @if ($check['new_count'] > 0)
                                <span class="new">{{ __(':aantal nieuw', ['aantal' => $check['new_count']]) }}</span>
                            @endif
                            @if ($check['resolved_count'] > 0)
                                <span class="up">{{ __(':aantal opgelost', ['aantal' => $check['resolved_count']]) }}</span>
                            @endif
                            <strong>{{ $check['count'] }}</strong>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="explain">
                        <td colspan="2">
                            <table>
                                <tr>
                                    <td>
                                        <div class="explain-label">{{ __('Wat er aan de hand is') }}</div>
                                        {{ $check['findings']['description'] }}
                                    </td>
                                    <td>
                                        <div class="explain-label">{{ __('Hoe je het oplost') }}</div>
                                        {{ $check['findings']['how_to_fix'] }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    @foreach ($check['findings']['rows'] as $row)
                        <tr class="finding">
                            <td colspan="2">
                                @if ($row['is_new'])
                                    <span class="new">{{ __('Nieuw') }}</span>
                                @endif
                                {{ $row['url'] }}
                                @php
                                    $context = collect($row['context'])
                                        ->filter(fn ($value) => is_scalar($value) && $value !== '' && $value !== null)
                                        ->map(fn ($value, $key) => $key . ': ' . \Illuminate\Support\Str::limit((string) $value, 80))
                                        ->implode(' · ');
                                @endphp
                                @if ($context !== '')
                                    <div class="context">{{ $context }}</div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endforeach
    </div>
@empty
    <p class="muted" style="margin-top: 18px;">{{ __('De scan heeft niets gevonden om te melden.') }}</p>
@endforelse
</body>
</html>
