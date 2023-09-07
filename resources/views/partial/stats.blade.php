<table class="table-auto w-full text-center">
    <tr>
        @foreach($stat as $name => $datum)
            <td>{{ $name }}: {!! $datum!!}</td>
        @endforeach
    </tr>
</table>
