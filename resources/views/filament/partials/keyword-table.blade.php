@php($showPage = $showPage ?? false)
<div class="dsp-table-wrap">
    <table class="dsp-table">
        <thead>
        <tr>
            <th>{{ __('Zoekterm') }}</th>
            @if($showPage)<th>{{ __('Pagina') }}</th>@endif
            <th class="dsp-num">{{ __('Klikken') }}</th>
            <th class="dsp-num">{{ __('Vertoningen') }}</th>
            <th class="dsp-num">{{ __('CTR') }}</th>
            <th class="dsp-num">{{ __('Positie') }}</th>
            <th class="dsp-num">{{ __('Vorige periode') }}</th>
        </tr>
        </thead>
        <tbody>
        @foreach($rows as $row)
            <tr>
                <td>{{ $row->search_query }}</td>
                @if($showPage)<td class="dsp-break">{{ $row->page_path }}</td>@endif
                <td class="dsp-num">{{ number_format($row->clicks, 0, ',', '.') }}</td>
                <td class="dsp-num">{{ number_format($row->impressions, 0, ',', '.') }}</td>
                <td class="dsp-num">{{ number_format($row->ctr, 1, ',', '.') }}%</td>
                <td class="dsp-num">{{ $row->position !== null ? number_format($row->position, 1, ',', '.') : '-' }}</td>
                <td class="dsp-num">
                    @if($row->position_change === null)
                        <span class="dsp-muted">{{ __('nieuw') }}</span>
                    @elseif($row->position_change > 0)
                        <span class="dsp-worse">{{ number_format($row->prev_position, 1, ',', '.') }} ({{ '+' . number_format($row->position_change, 1, ',', '.') }})</span>
                    @elseif($row->position_change < 0)
                        <span class="dsp-better">{{ number_format($row->prev_position, 1, ',', '.') }} ({{ number_format($row->position_change, 1, ',', '.') }})</span>
                    @else
                        {{ number_format($row->prev_position, 1, ',', '.') }}
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
