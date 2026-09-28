@include('partial.section_title', ['title' => $graphData['title']])
@isset($graphData['summary'])
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-3">
        @foreach($graphData['summary'] as $name => $value)
            <div class="bg-white rounded-xl border border-slate-200 px-4 py-3">
                <div class="text-xs uppercase tracking-wide text-slate-500">{{ $name }}</div>
                <div class="mt-1 text-lg font-semibold">{{ $value }}</div>
            </div>
        @endforeach
    </div>
@endisset
@isset($graphData['variants'])
    <div id="{{ $graphData['id'] ?? 'eventsChart' }}Kinds" class="flex gap-1 mb-3 text-sm" role="group" aria-label="Вид отключений">
        @foreach($graphData['variants'] as $kind => $variant)
            <button
                type="button"
                data-kind="{{ $kind }}"
                aria-pressed="{{ $loop->first ? 'true' : 'false' }}"
                class="px-3 py-1 rounded-full border border-slate-200 bg-white text-slate-600 hover:border-cyan-600 aria-pressed:bg-cyan-700 aria-pressed:border-cyan-700 aria-pressed:text-white"
            >{{ $variant['label'] }}</button>
        @endforeach
    </div>
@endisset
@php($chartId = $graphData['id'] ?? 'eventsChart')
<div class="bg-white rounded-xl border border-slate-200 p-3 h-[360px] md:h-[460px]">
    <canvas id="{{ $chartId }}" role="img" aria-label="{{ $graphData['title'] }}"></canvas>
</div>
<script>
    (() => {
        const graph = {!! json_encode($graphData) !!};
        const isBar = graph.type === 'bar';
        const unit = graph.yUnit ?? '';
        const ink = '#475569';
        const grid = '#e2e8f0';

        const style = (dataset) => {
            if (dataset.type === 'bar') {
                Object.assign(dataset, {
                    borderColor: '#ffffff',
                    borderWidth: {top: 1},
                    borderRadius: 2,
                    categoryPercentage: 1,
                    barPercentage: 0.9,
                    minBarLength: 0,
                });
            } else if (!dataset.type) {
                Object.assign(dataset, {borderWidth: 2, pointRadius: 0, pointHoverRadius: 5, tension: 0.2});
            }
        };
        graph.datasets.forEach(style);
        Object.values(graph.variants ?? {}).forEach((variant) => variant.datasets.forEach(style));

        // A dashed line at today's column separates history from announced outages.
        const todayLine = {
            id: 'todayLine',
            afterDatasetsDraw(chart) {
                if (graph.todayIndex === undefined) {
                    return;
                }
                const x = chart.scales.x.getPixelForValue(graph.todayIndex);
                const {top, bottom} = chart.chartArea;
                const c = chart.ctx;
                c.save();
                c.strokeStyle = ink;
                c.setLineDash([4, 4]);
                c.beginPath();
                c.moveTo(x, top);
                c.lineTo(x, bottom);
                c.stroke();
                c.setLineDash([]);
                c.fillStyle = ink;
                c.font = '12px system-ui, sans-serif';
                c.textAlign = 'right';
                c.fillText('сегодня', x - 4, top + 12);
                c.restore();
            },
        };

        const chart = new Chart(document.getElementById('{{ $chartId }}'), {
            type: graph.type ?? 'line',
            data: {labels: graph.labels, datasets: graph.datasets},
            plugins: [todayLine],
            options: {
                maintainAspectRatio: false,
                interaction: {mode: 'index', intersect: false},
                plugins: {
                    legend: {
                        position: 'top',
                        align: 'start',
                        labels: {usePointStyle: true, boxWidth: 8, color: ink, sort: (a, b) => a.datasetIndex - b.datasetIndex},
                    },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        padding: 10,
                        usePointStyle: true,
                        filter: (item) => item.raw !== null && (item.dataset.type !== 'bar' || item.raw > 0),
                        callbacks: {
                            label(item) {
                                const dataset = item.dataset;
                                if (dataset.kinds) {
                                    return ' Ваш адрес: ' + dataset.kinds[item.dataIndex];
                                }
                                if (dataset.counts) {
                                    return ` ${dataset.label}: ${item.parsed.y}${unit} (${dataset.counts[item.dataIndex]} адр.)`;
                                }
                                return ` ${dataset.label}: ${item.parsed.y}${unit}`;
                            },
                        },
                    },
                },
                scales: {
                    x: {
                        stacked: isBar,
                        grid: {display: false},
                        ticks: {color: ink, maxRotation: 0, autoSkip: true, maxTicksLimit: 8},
                    },
                    y: {
                        stacked: isBar,
                        beginAtZero: true,
                        suggestedMax: isBar ? 10 : undefined,
                        grid: {color: grid},
                        border: {display: false},
                        title: {display: true, text: graph.yTitle, color: ink},
                        ticks: {color: ink, callback: (value) => value + unit},
                    },
                    ...(graph.y1Title && {
                        y1: {
                            position: 'right',
                            beginAtZero: true,
                            suggestedMax: 10,
                            grid: {display: false},
                            border: {display: false},
                            title: {display: true, text: graph.y1Title, color: ink},
                            ticks: {color: ink, precision: 0},
                        },
                    }),
                },
            },
        });

        document.querySelectorAll('#{{ $chartId }}Kinds button').forEach((button, _, buttons) => {
            button.addEventListener('click', () => {
                buttons.forEach((other) => other.setAttribute('aria-pressed', String(other === button)));
                chart.data.datasets = graph.variants[button.dataset.kind].datasets;
                chart.update();
            });
        });
    })();
</script>
