@php
    use \Carbon\Carbon;

    Carbon::setLocale('ru');
@endphp

@extends('layout')
@section('title', 'Отключения адресу ' . $address->serviceCenter->name_ru . ', ' . $address->translit)

@section('content')
    @include('partial.stats', ['stat' => $stat])
    @include('partial.section_title', ['title' => 'Отключения по адресу', 'count' => count($address->events)])
    @include('partial.events_list', ['events' => $address->events])
    @include('chart', ['graphData' => $graphData])
@endsection

