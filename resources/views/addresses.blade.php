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
    <table class="table-auto w-full text-left overflow-x-scroll">
        <thead>
            <tr class="border">
                <th>Адрес</th>
                <th>Сервис центр</th>
                <th>Отключений</th>
            </tr>
        </thead>
        <tbody>
            @foreach($addresses as $address)
                <tr class="border">
                    <td>{{ $address->translit  }}</td>
                    <td>
                        <a
                            class="text-cyan-600"
                            href="{{ route('index', ['service_center_id' => $address->serviceCenter->id]) }}"
                        >
                            {{ $address->serviceCenter->name_ru  }}
                        </a>
                    </td>
                    <td>{{ $address->total_events  }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection

