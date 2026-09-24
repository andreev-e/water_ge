@include('partial.section_title', ['title' => $graphData['title']])
<div class="bg-white rounded-xl border border-slate-200 p-3 h-[420px] md:h-[560px]">
    <canvas id="eventsChart"></canvas>
</div>
<script>
    const ctx = document.getElementById('eventsChart');
    const labels = {!! json_encode($graphData['labels']) !!};
    const datasets = {!! json_encode($graphData['datasets']) !!};

    const data = {
        labels,
        datasets,
    };

    new Chart(ctx, {
        type: 'line',
        data: data,
        options: {
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: false,
                },
            },
            scales: {
                x: {
                    title: {
                        display: true,
                        text: '{{ $graphData['xTitle']  }}',
                    },
                },
                y: {
                    title: {
                        display: true,
                        text: '{{ $graphData['yTitle']  }}',
                    },
                },
            },
        },
    });
</script>
