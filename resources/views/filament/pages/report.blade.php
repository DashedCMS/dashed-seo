<x-filament-panels::page>
    @include('dashed-seo::filament.pages.partials.cover', ['cover' => $this->cover(), 'audit' => $record])

    <div class="mt-8 space-y-4">
        @foreach ($this->sections() as $section)
            @include('dashed-seo::filament.pages.partials.section', ['section' => $section])
        @endforeach
    </div>
</x-filament-panels::page>
