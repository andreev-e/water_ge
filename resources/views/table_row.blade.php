@php
    use App\Enums\EventTypes;
    use \Carbon\Carbon;

@endphp
<tr id="{{$event->id}}"
    class="border text-left {{ $event->start < Carbon::now() ? 'bg-yellow-50': ''}}">
    <td class="px-1">
        {!! $event->type->getIcon() !!}
    </td>
    <td class="px-1">
        <a class="text-cyan-600" href="/?service_center_id={{ $event->serviceCenter->id }}">
            {{ $event->serviceCenter->name_ru }}
        </a>
    </td>
    <td class="px-1">
        @if ($event->serviceCenter->total_addresses && $event->type !== EventTypes::gas)
            <b>{{ $event->total_addresses }} </b>
            (~{{ round($event->total_addresses / $event->serviceCenter->total_addresses * 100) }}%)
            {{ $event->effected_customers ? ' / ' . $event->effected_customers : '' }}
        @else
            -
        @endif
    </td>
    <td class="px-1">{{ $event->start->diffForHumans() }}</td>
    <td class="px-1">{{ $event->start < Carbon::now() ? $event->finish->diffForHumans(): $event->finish->diffForHumans($event->start)  }}</td>
    <td class="px-1">{{ $event->from_to }}</td>

    @if($withLink)
        <td class="p-1">
            <a
                href="{{ route('event', ['event' => $event->id]) }}"
                class="btn bg-slate-200 px-2 py-0.5 rounded"
            >
                Смотреть
            </a>
        </td>
    @endif
</tr>
