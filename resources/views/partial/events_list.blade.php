<div class="overflow-x-scroll">
    <table class="table-auto w-full text-left overflow-x-scroll">
        @include('table_head', ['withLink' => true])
        <tbody>
            @foreach($events as $event)
                @include('table_row', ['event' => $event, 'withLink' => true])
            @endforeach
        </tbody>
    </table>
</div>
