{{--
    De volledige was-wordt van een voorstel. Het hele veiligheidsargument voor
    de inhoudfixers is dat er een mens naar kijkt, en die mens had tot nu toe
    alleen een samenvatting van hoogstens 120 tekens. Voor een linkfixer staat
    daar alleen een voornemen in, niet wat er werkelijk verandert.
--}}
@php
    /** @var \Dashed\DashedSeo\Models\SiteAuditFix $fix */
    $before = $fix->before ?? [];
    $after = $fix->after ?? [];
    $velden = array_values(array_unique(array_merge(array_keys($before), array_keys($after))));

    $toon = function ($waarde): string {
        if ($waarde === null) {
            return '';
        }

        if (is_scalar($waarde)) {
            return (string) $waarde;
        }

        return (string) json_encode($waarde, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    };
@endphp

<div class="space-y-4 text-sm">
    <p class="text-gray-700 dark:text-gray-300">{{ $fix->summary }}</p>

    @if ($fix->locale)
        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Taal: :taal', ['taal' => $fix->locale]) }}</p>
    @endif

    @if ($velden === [])
        <p class="text-gray-500 dark:text-gray-400">{{ __('Dit voorstel legt geen veldwaarden vast.') }}</p>
    @endif

    @foreach ($velden as $veld)
        <div class="rounded-lg border border-gray-200 dark:border-gray-700">
            <div class="border-b border-gray-200 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:text-gray-400">
                {{ $veld }}
            </div>
            <div class="grid gap-3 p-3 md:grid-cols-2">
                <div>
                    <div class="mb-1 text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Nu') }}</div>
                    <pre class="max-h-64 overflow-auto whitespace-pre-wrap break-words rounded bg-gray-50 p-2 text-xs text-gray-800 dark:bg-gray-800 dark:text-gray-200">{{ $toon($before[$veld] ?? null) }}</pre>
                </div>
                <div>
                    <div class="mb-1 text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Wordt') }}</div>
                    <pre class="max-h-64 overflow-auto whitespace-pre-wrap break-words rounded bg-primary-50 p-2 text-xs text-gray-800 dark:bg-primary-950 dark:text-gray-200">{{ $toon($after[$veld] ?? null) }}</pre>
                </div>
            </div>
        </div>
    @endforeach

    @if ($fix->error)
        <div class="rounded-lg border border-danger-300 bg-danger-50 p-3 text-xs text-danger-800 dark:border-danger-800 dark:bg-danger-950 dark:text-danger-200">
            {{ $fix->error }}
        </div>
    @endif
</div>
