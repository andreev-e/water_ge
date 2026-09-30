<div class="bg-white rounded-xl border border-slate-200 overflow-x-auto">
    <table class="w-full text-sm text-left">
        @include('table_head', ['withLink' => true])
        <tbody data-live-rows>
            @foreach($events as $event)
                @include('table_row', ['event' => $event, 'withLink' => true])
            @endforeach
        </tbody>
    </table>
</div>
