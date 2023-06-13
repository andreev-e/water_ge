<h2 class="text-3xl text-center my-5">{{ $graphData['title']  }}</h2>
<div style="height: 600px">
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
                    text: '{{ $graphData['title'] }}',
                    display: true,
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
