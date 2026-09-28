@php
    use \Carbon\Carbon;

    Carbon::setLocale('ru');
@endphp

@extends('layout')
@section('title', $title)

@section('content')
    @include('partial.stats', ['stat' => $stat])
    @if (request()->has('service_center_id'))
        @include('partial.section_title', ['title' => 'Актуальные отключения', 'count' => count($currentEvents)])
        @include('partial.events_list', ['events' => $currentEvents])
    @else
        @php
            [$activeEvents, $upcomingEvents] = $currentEvents->partition(fn($event) => $event->start < Carbon::now());
        @endphp
        @include('partial.events_details', ['title' => 'Идут сейчас', 'events' => $activeEvents, 'open' => true, 'empty' => 'Сейчас отключений нет.'])
        @if ($upcomingEvents->isNotEmpty())
            @include('partial.events_details', ['title' => 'Будущие отключения', 'events' => $upcomingEvents])
        @endif
    @endif

    @if ($overview)
        @include('chart', ['graphData' => $overview])
    @endif

    @if ($graphData)
        @include('chart', ['graphData' => $graphData])
    @endif

    @if ($streetFilter)
        @include('chart', ['graphData' => $streetFilter])
    @endif

    @if ($addresses)
        @include('partial.section_title', ['title' => 'Часто отключаемые адреса'])
        @include('partial.addresses_list', ['addresses' => $addresses, 'withSC' => false])
    @endif

@endsection
