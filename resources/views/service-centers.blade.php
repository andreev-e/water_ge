@extends('layout')
@section('title', __('web.nav_centers'))

@section('content')
    @include('partial.stats', ['stat' => $stat])
    <div class="bg-white rounded-xl border border-slate-200 overflow-x-auto">
        <table class="w-full text-sm text-left">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-3 py-2 font-medium">{{ __('web.col_service_center') }}</th>
                    <th class="px-3 py-2 font-medium text-right">{{ __('web.col_outages') }}</th>
                    <th class="px-3 py-2 font-medium text-right">{{ __('web.col_subscribers') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($serviceCenters as $serviceCenter)
                    <tr class="border-t border-slate-100 hover:bg-slate-50">
                        <td class="px-3 py-2">
                            <a class="text-cyan-700 hover:underline" href="{{ \App\Support\Locale::route('service-center', ['serviceCenter' => $serviceCenter]) }}">
                                {{ $serviceCenter->localizedName(app()->getLocale()) }}
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

