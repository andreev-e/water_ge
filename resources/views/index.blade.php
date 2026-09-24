@php
    use \Carbon\Carbon;

    Carbon::setLocale('ru');
@endphp

@extends('layout')
@section('title', $title)

@section('content')
    @include('partial.stats', ['stat' => $stat])
    @include('partial.section_title', ['title' => 'Актуальные отключения', 'count' => count($currentEvents)])
    @include('partial.events_list', ['events' => $currentEvents])

    @if ($graphData)
        @include('chart', ['graphData' => $graphData])
    @endif

    @if ($addresses)
        @include('partial.section_title', ['title' => 'Часто отключаемые адреса'])
        @include('partial.addresses_list', ['addresses' => $addresses, 'withSC' => false])
    @endif

@endsection
