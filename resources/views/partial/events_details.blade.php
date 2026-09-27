<details class="group mt-8" @if($open ?? false) open @endif>
    <summary class="inline-flex items-center gap-2 cursor-pointer select-none text-lg font-semibold mb-3 list-none [&::-webkit-details-marker]:hidden">
        <span class="text-slate-400 transition-transform group-open:rotate-90">▸</span>
        {{ $title }}
        <span class="font-normal text-slate-400">{{ count($events) }}</span>
    </summary>
    @if (count($events))
        @include('partial.events_list', ['events' => $events])
    @else
        <p class="text-sm text-slate-500">{{ $empty }}</p>
    @endif
</details>
