<div class="bg-gray-50 px-6 py-4 dark:bg-gray-800/50">
    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Wat er aan de hand is') }}</div>
            <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ $findings['description'] }}</p>
        </div>
        <div>
            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Hoe je het oplost') }}</div>
            <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ $findings['how_to_fix'] }}</p>
        </div>
    </div>

    <ul class="mt-4 divide-y divide-gray-200 rounded-lg border border-gray-200 bg-white dark:divide-gray-700 dark:border-gray-700 dark:bg-gray-900">
        @foreach ($findings['rows'] as $row)
            <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-2 text-sm">
                <span class="flex min-w-0 items-center gap-2">
                    @if ($row['is_new'])
                        <span class="shrink-0 rounded bg-danger-100 px-1.5 py-0.5 text-xs font-medium text-danger-700 dark:bg-danger-950 dark:text-danger-300">{{ __('Nieuw') }}</span>
                    @endif
                    <a href="{{ $row['url'] }}" target="_blank" rel="noopener"
                       class="truncate text-primary-600 hover:underline dark:text-primary-400">{{ $row['url'] }}</a>
                </span>
                <span class="flex shrink-0 items-center gap-3">
                    @foreach ($row['context'] as $key => $value)
                        @if (is_scalar($value) && $value !== '' && $value !== null)
                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ $key }}: {{ \Illuminate\Support\Str::limit((string) $value, 60) }}</span>
                        @endif
                    @endforeach
                    @if ($row['edit_url'])
                        <a href="{{ $row['edit_url'] }}" class="text-xs font-medium text-primary-600 hover:underline dark:text-primary-400">{{ __('Bewerken') }}</a>
                    @endif
                </span>
            </li>
        @endforeach
    </ul>

    @if ($findings['remaining'] > 0)
        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
            {{ __('Nog :aantal adressen niet getoond. Gebruik het scherm per pagina om alles te doorlopen.', ['aantal' => $findings['remaining']]) }}
        </p>
    @endif
</div>
