@php
    use \Carbon\Carbon;

    Carbon::setLocale('ru');
@endphp

@extends('layout')
@section('title', $title)

@section('content')
    @include('partial.stats', ['stat' => $stat])
    <h2 class="text-3xl text-center my-5">
        Актуальные отключения
        ({{ count($currentEvents) }})
    </h2>
    @include('partial.events_list', ['events' => $currentEvents])

    @if ($graphData)
        @include('chart', ['graphData' => $graphData])
    @endif

    @if ($addresses)
        <h2 class="text-3xl text-center my-5">Часто отключаемые адреса</h2>
        @include('partial.addresses_list', ['addresses' => $addresses, 'withSC' => false])
    @endif

@endsection
