<section class="mb-8">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        @foreach($stat as $name => $datum)
            <div class="bg-white rounded-xl border border-slate-200 px-4 py-3">
                <div class="text-xs uppercase tracking-wide text-slate-500">{{ $name }}</div>
                <div class="mt-1 text-xl font-semibold">{!! $datum !!}</div>
            </div>
        @endforeach
    </div>
    <div class="mt-3 flex flex-wrap justify-center gap-2 text-sm text-slate-500">
        @foreach($sources as $source => $updatedAt)
            @php($stale = \App\Support\SourceStatus::isStale($updatedAt))
            <span
                class="px-3 py-1 rounded-full bg-white border border-slate-200"
                title="{{ $updatedAt ? 'Последнее успешное обновление: ' . $updatedAt->format('d.m.Y H:i') : 'Ещё не обновлялся' }}"
            >
                <span class="inline-block w-2 h-2 rounded-full align-middle {{ $stale ? 'bg-red-500' : 'bg-green-500' }}"></span>
                {{ \App\Support\SourceStatus::LABELS[$source] }}:
                <span class="{{ $stale ? 'text-red-600' : '' }}">{{ $updatedAt?->locale('ru')->diffForHumans() ?? 'нет данных' }}</span>
            </span>
        @endforeach
    </div>
</section>
