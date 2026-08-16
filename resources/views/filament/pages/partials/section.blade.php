<div x-data="{ open: {{ $section['total'] > 0 ? 'true' : 'false' }} }"
     class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
    <button type="button" x-on:click="open = ! open"
            class="flex w-full items-center justify-between gap-4 px-6 py-4 text-left">
        <span class="font-medium text-gray-950 dark:text-white">{{ $section['label'] }}</span>
        <span class="flex items-center gap-3 text-sm text-gray-500 dark:text-gray-400">
            @if ($section['total'] > 0)
                {{ trans_choice('{1}:aantal probleem|[2,*]:aantal problemen', $section['total'], ['aantal' => $section['total']]) }}
            @else
                {{ __('Niets gevonden') }}
            @endif
            <x-filament::icon icon="heroicon-o-chevron-down" class="h-4 w-4" x-bind:class="open && 'rotate-180'" />
        </span>
    </button>

    <div x-show="open" x-cloak class="border-t border-gray-200 dark:border-gray-700">
        @forelse ($section['checks'] as $check)
            <div class="border-b border-gray-100 last:border-b-0 dark:border-gray-800">
                <button type="button" wire:click="toggleCheck('{{ $check['check_key'] }}')"
                        class="flex w-full items-center justify-between gap-4 px-6 py-3 text-left text-sm hover:bg-gray-50 dark:hover:bg-gray-800">
                    <span class="flex items-center gap-2">
                        <span @class([
                            'inline-block h-2 w-2 shrink-0 rounded-full',
                            'bg-danger-500' => $check['severity'] === 'error',
                            'bg-warning-500' => $check['severity'] === 'warning',
                            'bg-gray-400' => $check['severity'] === 'notice',
                        ])></span>
                        <span class="text-gray-700 dark:text-gray-300">{{ $check['label'] }}</span>
                    </span>
                    <span class="flex shrink-0 items-center gap-3 text-gray-500 dark:text-gray-400">
                        @if ($check['new_count'] > 0)
                            <span class="text-danger-600 dark:text-danger-400">{{ __(':aantal nieuw', ['aantal' => $check['new_count']]) }}</span>
                        @endif
                        @if ($check['resolved_count'] > 0)
                            <span class="text-success-600 dark:text-success-400">{{ __(':aantal opgelost', ['aantal' => $check['resolved_count']]) }}</span>
                        @endif
                        <span class="font-medium text-gray-950 dark:text-white">{{ $check['count'] }}</span>
                    </span>
                </button>

                @if ($this->openCheck === $check['check_key'])
                    @include('dashed-seo::filament.pages.partials.finding-list', ['findings' => $this->openFindings()])
                @endif
            </div>
        @empty
            <p class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ __('In deze sectie is niets gevonden.') }}</p>
        @endforelse
    </div>
</div>
