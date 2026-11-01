@extends('layout')
@section('title', __('web.publications.title'))

@section('content')
    @include('partial.stats', ['stat' => $stat])

    <p class="mb-4 text-sm text-slate-500">{{ __('web.publications.subtitle') }}</p>

    @if($publications->isEmpty())
        <div class="bg-white rounded-xl border border-slate-200 p-6 text-sm text-slate-500">
            {{ __('web.publications.empty') }}
        </div>
    @else
        <div class="grid gap-3 md:grid-cols-2">
            @foreach($publications as $publication)
                @php($translation = $publication->translation(app()->getLocale()))
                @continue($translation === null)
                <article class="bg-white rounded-xl border border-slate-200 p-4 hover:border-cyan-600">
                    <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
                        <span class="px-2 py-0.5 rounded-full bg-slate-100">{{ __('web.publications.' . $publication->type) }}</span>
                        <span>{{ $publication->periodLabel(app()->getLocale()) }}</span>
                    </div>
                    <h2 class="mt-2 text-base font-semibold">
                        <a class="hover:text-cyan-700" href="{{ $publication->url() }}">{{ $translation->title }}</a>
                    </h2>
                    <p class="mt-1 text-sm text-slate-600">{{ $translation->excerpt }}</p>
                    <a class="mt-2 inline-block text-sm text-cyan-700 hover:underline" href="{{ $publication->url() }}">
                        {{ __('web.publications.read') }}
                    </a>
                </article>
            @endforeach
        </div>
    @endif
@endsection
