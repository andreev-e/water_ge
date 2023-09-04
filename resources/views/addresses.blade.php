@php
    use \Carbon\Carbon;

    Carbon::setLocale('ru');
@endphp

@extends('layout')
@section('title', 'Сервис центры')

@section('content')
    <table class="table-auto w-full text-center">
        <tr>
            @foreach($stat as $name => $datum)
                <td>{{ $name }}: {!! $datum !!}</td>
            @endforeach
        </tr>
    </table>
    <h2>Часто отключаемые адреса</h2>
    @include('addresses_list', ['addresses' => $addresses])
@endsection

