@php
    use App\Support\Locale;
    use App\Support\StatsFormat;

    $locale = app()->getLocale();
    $statistics = $publication->statistics ?? [];
    $outages = $statistics['outages'] ?? [];
    $numbers = array_filter([
        __('web.publications.total') => isset($outages['total']) ? StatsFormat::number($outages['total'], $locale) : null,
        __('web.publications.change') => isset($outages['change_percent']) ? StatsFormat::change($outages['change_percent'], $locale) : null,
        __('web.publications.addresses') => isset($outages['addresses']) ? StatsFormat::number($outages['addresses'], $locale) : null,
        __('web.publications.customers') => isset($outages['customers']) ? StatsFormat::number($outages['customers'], $locale) : null,
        __('web.publications.median_duration') => isset($outages['median_duration_hours']) ? StatsFormat::number($outages['median_duration_hours'], $locale, 1) : null,
        __('web.publications.emergency_share') => isset($outages['emergency_share']) ? StatsFormat::percent($outages['emergency_share'], $locale) : null,
    ], fn (?string $value) => $value !== null && $value !== '—');
@endphp

@extends('layout')
@section('title', $translation->title)

@section('content')
    @include('partial.stats', ['stat' => $stat])

    <nav class="mb-3 text-sm">
        <a class="text-cyan-700 hover:underline" href="{{ Locale::route('publications') }}">← {{ __('web.publications.back') }}</a>
    </nav>

    <article class="bg-white rounded-xl border border-slate-200 p-4 md:p-6">
        <header>
            <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
                <span class="px-2 py-0.5 rounded-full bg-slate-100">{{ __('web.publications.' . $publication->type) }}</span>
                <span>{{ $publication->periodLabel($locale) }}</span>
                @if($publication->published_at)
                    <span>· {{ __('web.publications.published', ['date' => $publication->published_at->locale($locale)->translatedFormat('j F Y')]) }}</span>
                @endif
            </div>
            <h2 class="mt-2 text-xl font-semibold">{{ $translation->title }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ $translation->excerpt }}</p>
        </header>

        <div class="mt-4 space-y-3 text-sm leading-relaxed">
            @foreach($translation->blocks() as $block)
                @if($block['list'])
                    <ul class="list-disc pl-5 space-y-1">
                        @foreach($block['lines'] as $line)
                            <li>{{ $line }}</li>
                        @endforeach
                    </ul>
                @else
                    <p>{{ $block['text'] }}</p>
                @endif
            @endforeach
        </div>

        @if($numbers !== [])
            <h3 class="mt-6 mb-2 text-sm font-semibold uppercase tracking-wide text-slate-500">
                {{ __('web.publications.key_numbers') }}
            </h3>
            <dl class="grid grid-cols-2 gap-2 md:grid-cols-3">
                @foreach($numbers as $label => $value)
                    <div class="rounded-lg bg-slate-50 border border-slate-100 px-3 py-2">
                        <dt class="text-xs text-slate-500">{{ $label }}</dt>
                        <dd class="text-lg font-semibold tabular-nums">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        @endif

        @if(!empty($outages['top_centers']))
            <h3 class="mt-6 mb-2 text-sm font-semibold uppercase tracking-wide text-slate-500">
                {{ __('web.publications.top_centers') }}
            </h3>
            <ul class="text-sm space-y-1">
                @foreach($outages['top_centers'] as $center)
                    <li class="flex justify-between gap-3 border-b border-slate-100 pb-1">
                        <a class="text-cyan-700 hover:underline" href="{{ Locale::route('service-center', ['serviceCenter' => $center['id']]) }}">
                            {{ $center['names'][$locale] ?? $center['names'][Locale::WEB_DEFAULT] ?? '' }}
                        </a>
                        <span class="tabular-nums text-slate-500">{{ StatsFormat::number($center['events'], $locale) }}</span>
                    </li>
                @endforeach
            </ul>
        @endif

        @if(!empty($outages['top_addresses']))
            <h3 class="mt-6 mb-2 text-sm font-semibold uppercase tracking-wide text-slate-500">
                {{ __('web.publications.top_addresses') }}
            </h3>
            <ul class="text-sm space-y-1">
                @foreach($outages['top_addresses'] as $address)
                    <li class="flex justify-between gap-3 border-b border-slate-100 pb-1">
                        <a class="text-cyan-700 hover:underline" href="{{ Locale::route('address', ['address' => $address['id']]) }}">
                            {{ $address['names'][$locale] ?? $address['names'][Locale::WEB_DEFAULT] ?? '' }}
                        </a>
                        <span class="tabular-nums text-slate-500">{{ StatsFormat::number($address['events'], $locale) }}</span>
                    </li>
                @endforeach
            </ul>
        @endif

        <p class="mt-6 text-xs text-slate-400">{{ __('web.publications.data_source') }}</p>
    </article>
@endsection
