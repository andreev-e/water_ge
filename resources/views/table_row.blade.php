@php
    use App\Enums\EventTypes;
    use \Carbon\Carbon;
    use \Carbon\CarbonInterface;
    use App\Support\Locale;

    $active = $event->start < Carbon::now();
@endphp
<tr
    id="{{$event->id}}"
    class="border-t border-slate-100 hover:bg-slate-50 {{ $active ? 'bg-amber-50/60' : '' }}"
    data-start="{{ $event->start->valueOf() }}"
    data-finish="{{ $event->finish->valueOf() }}"
>
    <td class="px-3 py-2 w-px whitespace-nowrap">
        <a href="{{ Locale::route('index', ['type' => $event->type->value]) }}" title="{{ __('web.only_this_type') }}">
            {!! $event->type->getIcon() !!}
        </a>
    </td>
    <td class="px-3 py-2">
        <a class="text-cyan-700 hover:underline" href="{{ Locale::route('service-center', ['serviceCenter' => $event->serviceCenter]) }}">
            {{ $event->serviceCenter->localizedName(app()->getLocale()) }}
        </a>
        @if ($event->planned === false)
            <span
                class="ml-1 inline-flex items-center justify-center w-5 h-5 align-middle rounded-full bg-red-50 text-red-600 cursor-help"
                title="{{ __('web.emergency_outage') }}"
                aria-label="{{ __('web.emergency_outage') }}"
            >
                <svg class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M8.49 2.87c.67-1.16 2.35-1.16 3.02 0l6.28 10.88c.67 1.16-.17 2.62-1.51 2.62H3.72c-1.34 0-2.18-1.46-1.51-2.62L8.49 2.87ZM10 6.5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 6.5Zm0 7.5a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/>
                </svg>
            </span>
        @endif
        @if($withLink)
            <a
                href="{{ Locale::route('event', ['event' => $event->id]) }}"
                class="ml-1 inline-flex items-center justify-center w-5 h-5 align-middle rounded-full bg-slate-100 text-slate-500 hover:bg-cyan-50 hover:text-cyan-700"
                title="{{ __('web.details') }}"
                aria-label="{{ __('web.details') }}"
            >
                <svg class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M7.2 14.8a.75.75 0 0 1 0-1.06L10.94 10 7.2 6.26a.75.75 0 1 1 1.06-1.06l4.27 4.27a.75.75 0 0 1 0 1.06L8.26 14.8a.75.75 0 0 1-1.06 0Z" clip-rule="evenodd"/>
                </svg>
            </a>
        @endif
    </td>
    <td class="px-3 py-2 whitespace-nowrap text-slate-600">
        @if ($event->finish->isPast())
            <span class="text-slate-500">{{ $event->fromToIn(app()->getLocale()) }}</span>
        @else
            {{-- Shared timeline: "now" sits at the same x in every row, bar length is proportional to duration. --}}
            <div
                class="event-progress relative flex items-center justify-center h-6 min-w-[20rem] px-2 overflow-hidden rounded border border-slate-300 bg-slate-100 text-xs"
                data-start="{{ $event->start->valueOf() }}"
                data-finish="{{ $event->finish->valueOf() }}"
            >
                <div class="event-progress-span absolute inset-y-0 bg-amber-200/80 transition-[left,width] duration-1000 ease-linear" style="left: 0; width: 0"></div>
                <div class="event-progress-fill absolute inset-y-0 bg-amber-400/70 transition-[left,width] duration-1000 ease-linear" style="left: 0; width: 0"></div>
                <div class="event-progress-marker absolute inset-y-0 w-0.5 -ml-px bg-amber-600 transition-[left] duration-1000 ease-linear">
                    <span class="absolute inset-0 bg-amber-500 animate-ping"></span>
                </div>
                <span class="relative z-10">{{ $event->fromToIn(app()->getLocale()) }}</span>
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
    {{-- data-countdown: under a minute to go, the text turns into a live seconds countdown. --}}
    <td class="px-3 py-2 whitespace-nowrap {{ $active ? 'text-amber-700' : '' }}" data-countdown="start">{{ $event->start->diffForHumans() }}</td>
    <td
        class="px-3 py-2 whitespace-nowrap"
        data-countdown="finish"
        {{-- Shown once the outage starts and the row moves to the current ones without a reload. --}}
        @unless($active) data-active-text="{{ $event->finish->diffForHumans($event->start, CarbonInterface::DIFF_RELATIVE_TO_NOW) }}" @endunless
    >{{ $active ? $event->finish->diffForHumans() : $event->finish->diffForHumans($event->start) }}</td>
</tr>
