@if(count($sites) > 1)
    <select class="dsp-select" wire:model.live="siteId" aria-label="{{ __('Site') }}">
        @foreach($sites as $site)
            <option value="{{ $site['id'] }}">{{ ucfirst($site['name']) }}</option>
        @endforeach
    </select>
@endif
