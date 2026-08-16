<div class="space-y-6">
    @foreach ($cover['warnings_list'] as $warning)
        <div class="rounded-lg border border-warning-300 bg-warning-50 p-4 text-sm text-warning-800 dark:border-warning-700 dark:bg-warning-950 dark:text-warning-200">
            {{ $warning }}
        </div>
    @endforeach

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('Technische score') }}</div>
            <div class="mt-1 text-4xl font-semibold text-gray-950 dark:text-white">{{ $cover['score'] ?? '-' }}</div>
            @if ($cover['delta'] !== null)
                {{-- De beweging staat groter dan het cijfer zelf, want de
                     score van een gezonde site schommelt maar een paar punten
                     en zegt in zijn eentje weinig. --}}
                <div @class([
                    'mt-2 text-sm font-medium',
                    'text-success-600 dark:text-success-400' => $cover['delta'] > 0,
                    'text-danger-600 dark:text-danger-400' => $cover['delta'] < 0,
                    'text-gray-500 dark:text-gray-400' => $cover['delta'] === 0,
                ])>
                    {{ $cover['delta'] > 0 ? '+' : '' }}{{ $cover['delta'] }}
                    {{ __('ten opzichte van de scan van :datum', ['datum' => $cover['previous_finished_at']?->format('d-m-Y')]) }}
                </div>
            @else
                <div class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('Eerste scan van deze site') }}</div>
            @endif
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('Gevonden problemen') }}</div>
            <dl class="mt-2 space-y-1 text-sm">
                <div class="flex justify-between"><dt class="text-danger-600 dark:text-danger-400">{{ __('Fouten') }}</dt><dd class="font-medium">{{ $cover['errors'] }}</dd></div>
                <div class="flex justify-between"><dt class="text-warning-600 dark:text-warning-400">{{ __('Waarschuwingen') }}</dt><dd class="font-medium">{{ $cover['warnings'] }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">{{ __('Notities') }}</dt><dd class="font-medium">{{ $cover['notices'] }}</dd></div>
            </dl>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('Opgehaalde pagina\'s') }}</div>
            <div class="mt-1 text-4xl font-semibold text-gray-950 dark:text-white">{{ $cover['pages_crawled'] }}</div>
            <div class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                {{ __(':aantal adressen gevonden', ['aantal' => $cover['urls_found']]) }}
                @if ($cover['pages_blocked'] > 0)
                    , {{ __(':aantal afgeschermd', ['aantal' => $cover['pages_blocked']]) }}
                @endif
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('Grootste problemen') }}</div>
            <ol class="mt-2 space-y-1 text-sm">
                @forelse ($cover['top'] as $item)
                    <li class="flex items-start justify-between gap-2">
                        <span class="text-gray-700 dark:text-gray-300">{{ $item['label'] }}</span>
                        <span class="shrink-0 font-medium">{{ $item['count'] }}</span>
                    </li>
                @empty
                    <li class="text-gray-500 dark:text-gray-400">{{ __('Niets gevonden') }}</li>
                @endforelse
            </ol>
        </div>
    </div>
</div>
