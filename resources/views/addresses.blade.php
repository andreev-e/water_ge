@extends('layout')
@section('title', __('web.frequent_addresses'))

@section('content')
    @include('partial.stats', ['stat' => $stat])
    @include('partial.addresses_list', ['addresses' => $addresses, 'withSC' => true])
@endsection

