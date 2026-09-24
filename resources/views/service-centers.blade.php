@php
    use \Carbon\Carbon;

    Carbon::setLocale('ru');
@endphp

@extends('layout')
@section('title', 'Сервис центры')

@section('content')
    @include('partial.stats', ['stat' => $stat])
    <div class="bg-white rounded-xl border border-slate-200 overflow-x-auto">
        <table class="w-full text-sm text-left">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-3 py-2 font-medium">Сервис центр</th>
                    <th class="px-3 py-2 font-medium text-right">Отключений</th>
                    <th class="px-3 py-2 font-medium text-right">Подписчиков</th>
                </tr>
            </thead>
            <tbody>
                @foreach($serviceCenters as $serviceCenter)
                    <tr class="border-t border-slate-100 hover:bg-slate-50">
                        <td class="px-3 py-2">
                            <a class="text-cyan-700 hover:underline" href="{{ route('index', ['service_center_id' => $serviceCenter->id]) }}">
                                {{ $serviceCenter->name_ru }}
                            </a>
                        </td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ $serviceCenter->total_events }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ $serviceCenter->subscriptions_count }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection

