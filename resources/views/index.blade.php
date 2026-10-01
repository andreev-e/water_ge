@extends('layout')
@section('title', $title)

@section('content')
    @include('partial.stats', ['stat' => $stat])
    @include('partial.current_events', ['currentEvents' => $currentEvents])

    @if ($overview)
        @include('chart', ['graphData' => $overview])
    @endif

    @if ($graphData)
        @include('chart', ['graphData' => $graphData])
    @endif

    @if ($addresses)
        @include('partial.section_title', ['title' => __('web.frequent_addresses')])
        @include('partial.addresses_list', ['addresses' => $addresses, 'withSC' => false])
    @endif

@endsection
