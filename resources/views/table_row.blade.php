@php
    use App\Enums\EventTypes;
    use \Carbon\Carbon;

    $active = $event->start < Carbon::now();
    // Initial bar position, so rows inserted by live refresh don't jump from zero.
    $progress = round(min(1, max(0, $event->start->diffInSeconds(Carbon::now(), false) / max(1, $event->start->diffInSeconds($event->finish)))) * 100, 2) . '%';
@endphp
<tr id="{{$event->id}}" class="border-t border-slate-100 hover:bg-slate-50 {{ $active ? 'bg-amber-50/60' : '' }}">
    <td class="px-3 py-2 w-px whitespace-nowrap">
        <a href="/?type={{ $event->type->value }}" title="Только этот тип">
            {!! $event->type->getIcon() !!}
        </a>
    </td>
    <td class="px-3 py-2">
        <a class="text-cyan-700 hover:underline" href="/?service_center_id={{ $event->serviceCenter->id }}">
            {{ $event->serviceCenter->name_ru }}
        </a>
        @if ($event->planned === false)
            <span class="ml-1 px-1.5 py-0.5 rounded bg-red-50 text-xs text-red-700 whitespace-nowrap" title="Аварийное отключение">авария</span>
        @endif
        @if($withLink)
            <a
                href="{{ route('event', ['event' => $event->id]) }}"
                class="ml-1 px-2 py-0.5 rounded-full bg-slate-100 text-xs text-slate-500 hover:bg-cyan-50 hover:text-cyan-700 whitespace-nowrap"
            >
                подробнее
            </a>
        @endif
    </td>
    <td class="px-3 py-2 whitespace-nowrap text-slate-600">
        @if ($event->finish->isPast())
            <span class="text-slate-500">{{ $event->from_to }}</span>
        @else
            <div
                class="event-progress relative flex items-center justify-center h-6 min-w-[14rem] px-2 overflow-hidden rounded border border-slate-300 bg-slate-100 text-xs"
                data-start="{{ $event->start->valueOf() }}"
                data-finish="{{ $event->finish->valueOf() }}"
            >
                <div class="event-progress-fill absolute inset-y-0 left-0 bg-amber-300/70 transition-[width] duration-1000 ease-linear" style="width: {{ $progress }}"></div>
                <div class="event-progress-marker absolute inset-y-0 w-0.5 -ml-px bg-amber-600 transition-[left] duration-1000 ease-linear" style="left: {{ $progress }}">
                    <span class="absolute inset-0 bg-amber-500 animate-ping"></span>
                </div>
                <span class="relative z-10">{{ $event->from_to }}</span>
            </div>
        @endif
    </td>
    <td class="px-3 py-2 whitespace-nowrap">
        @if ($event->serviceCenter->total_addresses && $event->type !== EventTypes::gas)
            <span class="font-semibold">{{ $event->total_addresses }}</span>
            <span class="text-slate-400">~{{ round($event->total_addresses / $event->serviceCenter->total_addresses * 100) }}%</span>
            @if ($event->effected_customers)
                <span class="text-slate-500">/ {{ $event->effected_customers }}</span>
            @endif
        @else
            <span class="text-slate-400">—</span>
        @endif
    </td>
    <td class="px-3 py-2 whitespace-nowrap {{ $active ? 'text-amber-700' : '' }}">{{ $event->start->diffForHumans() }}</td>
    <td class="px-3 py-2 whitespace-nowrap">{{ $active ? $event->finish->diffForHumans() : $event->finish->diffForHumans($event->start) }}</td>
</tr>
