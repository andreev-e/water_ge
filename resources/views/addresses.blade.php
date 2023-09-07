@php
    use \Carbon\Carbon;

    Carbon::setLocale('ru');
@endphp

@extends('layout')
@section('title', 'Часто отключаемые адреса')

@section('content')
    <table class="table-auto w-full text-center">
        <tr>
            @foreach($stat as $name => $datum)
                <td>{{ $name }}: {!! $datum !!}</td>
            @endforeach
        </tr>
    </table>
    @include('partial.addresses_list', ['addresses' => $addresses, 'withSC' => true])'])
@endsection

