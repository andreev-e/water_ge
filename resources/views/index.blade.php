@php
    use \Carbon\Carbon;

    Carbon::setLocale('ru');
@endphp

@extends('layout')
@section('title', $title)

@section('content')
    @include('partial.stats', ['stat' => $stat])
    @php
        [$activeEvents, $upcomingEvents] = $currentEvents->partition(fn($event) => $event->start < Carbon::now());
    @endphp
    @include('partial.section_title', ['title' => 'Идут сейчас', 'count' => count($activeEvents)])
    @if ($activeEvents->isNotEmpty())
        @include('partial.events_list', ['events' => $activeEvents])
    @else
        <p class="text-sm text-slate-500">Сейчас отключений нет.</p>
    @endif

    @if ($upcomingEvents->isNotEmpty())
        <details class="group mt-4">
            <summary class="inline-flex items-center gap-2 cursor-pointer select-none text-lg font-semibold mb-3 list-none [&::-webkit-details-marker]:hidden">
                <span class="text-slate-400 transition-transform group-open:rotate-90">▸</span>
                Будущие отключения
                <span class="font-normal text-slate-400">{{ count($upcomingEvents) }}</span>
            </summary>
            @include('partial.events_list', ['events' => $upcomingEvents])
        </details>
    @endif

    @if ($overview)
        @include('chart', ['graphData' => $overview])
    @endif

    @if ($graphData)
        @include('chart', ['graphData' => $graphData])
    @endif

    @if ($addresses)
        @include('partial.section_title', ['title' => 'Часто отключаемые адреса'])
        @include('partial.addresses_list', ['addresses' => $addresses, 'withSC' => false])
    @endif

@endsection
