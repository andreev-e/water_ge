@php
    use App\Enums\EventTypes;
    use \Carbon\Carbon;

    $active = $event->start < Carbon::now();
@endphp
<tr id="{{$event->id}}" class="border-t border-slate-100 hover:bg-slate-50 {{ $active ? 'bg-amber-50/60' : '' }}">
    <td class="px-3 py-2 whitespace-nowrap">
        <span
            class="inline-block w-2 h-2 rounded-full align-middle {{ $active ? 'bg-amber-500' : 'bg-slate-300' }}"
            title="{{ $active ? 'Идёт сейчас' : 'Запланировано' }}"
        ></span>
        <a class="ml-1" href="/?type={{ $event->type->value }}" title="Только этот тип">
            {!! $event->type->getIcon() !!}
        </a>
    </td>
    <td class="px-3 py-2">
        <a class="text-cyan-700 hover:underline" href="/?service_center_id={{ $event->serviceCenter->id }}">
            {{ $event->serviceCenter->name_ru }}
        </a>
        @if($withLink)
            <a
                href="{{ route('event', ['event' => $event->id]) }}"
                class="ml-1 px-2 py-0.5 rounded-full bg-slate-100 text-xs text-slate-500 hover:bg-cyan-50 hover:text-cyan-700 whitespace-nowrap"
            >
                подробнее
            </a>
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
    <td class="px-3 py-2 whitespace-nowrap text-slate-500">{{ $event->from_to }}</td>
</tr>
