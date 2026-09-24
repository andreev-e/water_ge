@php
    use \Carbon\Carbon;

    Carbon::setLocale('ru');
@endphp

@extends('layout')
@section('title', 'Часто отключаемые адреса')

@section('content')
    @include('partial.stats', ['stat' => $stat])
    @include('partial.addresses_list', ['addresses' => $addresses, 'withSC' => true])'])
@endsection

