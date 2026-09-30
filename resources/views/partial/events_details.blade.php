@php
    $hidden = count($events) === 0 && ($hideEmpty ?? false);
@endphp
<details
    class="group mt-8 {{ $hidden ? 'hidden' : '' }}"
    @if($open ?? false) open @endif
    @isset($key) data-live-section="{{ $key }}" @endisset
    @if($hideEmpty ?? false) data-live-hide-empty @endif
>
    <summary class="inline-flex items-center gap-2 cursor-pointer select-none text-lg font-semibold mb-3 list-none [&::-webkit-details-marker]:hidden">
        <span class="text-slate-400 transition-transform group-open:rotate-90">▸</span>
        {{ $title }}
        <span class="font-normal text-slate-400" data-live-count>{{ count($events) }}</span>
    </summary>
    <div class="{{ count($events) ? '' : 'hidden' }}" data-live-list>
        @include('partial.events_list', ['events' => $events])
    </div>
    @isset($empty)
        <p class="text-sm text-slate-500 {{ count($events) ? 'hidden' : '' }}" data-live-empty>{{ $empty }}</p>
    @endisset
</details>
