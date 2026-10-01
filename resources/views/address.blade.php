@extends('layout')
@section('title', __('web.title_address', ['center' => $address->serviceCenter->localizedName(app()->getLocale()), 'address' => $address->localizedName(app()->getLocale())]))

@section('content')
    @include('partial.stats', ['stat' => $stat])
    @include('partial.section_title', ['title' => __('web.address_events'), 'count' => count($address->events)])
    @include('partial.events_list', ['events' => $address->events])
    @include('chart', ['graphData' => $graphData])
@endsection

