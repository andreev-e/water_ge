<table class="table-auto w-full text-center">
    <tr>
        @foreach($stat as $name => $datum)
            <td>{{ $name }}: {!! $datum!!}</td>
        @endforeach
    </tr>
</table>
<div class="flex flex-wrap justify-center gap-x-4 gap-y-1 text-sm text-gray-500 mb-2">
    @foreach($sources as $source => $updatedAt)
        @php($stale = \App\Support\SourceStatus::isStale($updatedAt))
        <span title="{{ $updatedAt ? 'Последнее успешное обновление: ' . $updatedAt->format('d.m.Y H:i') : 'Ещё не обновлялся' }}">
            <span class="inline-block w-2 h-2 rounded-full align-middle {{ $stale ? 'bg-red-500' : 'bg-green-500' }}"></span>
            {{ \App\Support\SourceStatus::LABELS[$source] }}:
            <span class="{{ $stale ? 'text-red-600' : '' }}">{{ $updatedAt?->locale('ru')->diffForHumans() ?? 'нет данных' }}</span>
        </span>
    @endforeach
</div>
