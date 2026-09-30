<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
    <title>@yield('title')</title>
    <link rel="icon" type="image/svg+xml" href="/logo.svg">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">
    <meta name="theme-color" content="#0e7490">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-slate-50 text-slate-800 antialiased">
<div class="max-w-6xl mx-auto px-4 py-6">
    <header class="mb-6 text-center">
        <div class="flex items-center justify-center gap-3">
            <a href="{{ route('index') }}" class="shrink-0"><img src="/logo.svg" alt="WaterGeorgia" class="w-10 h-10 md:w-12 md:h-12"></a>
            <h1 class="text-2xl md:text-3xl font-semibold tracking-tight text-left">@yield('title')</h1>
        </div>
        <nav class="mt-4 flex flex-wrap justify-center gap-2 text-sm">
            <a href="{{ route('index') }}" class="px-3 py-1 rounded-full bg-white border border-slate-200 hover:border-cyan-600 hover:text-cyan-700">Все отключения</a>
            <a href="{{ route('service-centers') }}" class="px-3 py-1 rounded-full bg-white border border-slate-200 hover:border-cyan-600 hover:text-cyan-700">Сервис центры</a>
            <a href="{{ route('addresses') }}" class="px-3 py-1 rounded-full bg-white border border-slate-200 hover:border-cyan-600 hover:text-cyan-700">Адреса</a>
            <a target="_blank" href="https://t.me/WaterGeorgia_bot" class="px-3 py-1 rounded-full bg-cyan-700 text-white hover:bg-cyan-800">
                Telegram-бот @WaterGeorgia_bot
            </a>
            <a target="_blank" href="https://www.facebook.com/profile.php?id=61594858257140" class="px-3 py-1 rounded-full bg-blue-600 text-white hover:bg-blue-700">
                Facebook
            </a>
        </nav>
        <p class="mt-2 text-xs text-slate-500">
            В Telegram-боте можно подписаться на любой сервисный центр (город) или адрес и получать уведомления об отключениях
        </p>
    </header>
    @yield('content')
</div>
<script>
    // Draws every outage on one shared timeline: "now" is at the same x in every row,
    // and bar length is proportional to the outage duration.
    (function () {
        if (!document.querySelector('.event-progress')) {
            return;
        }

        const HOUR = 3600000;
        const NOW_AT = 0.25; // share of the bar width left of "now"
        const MIN_SPAN = 2 * HOUR;
        const MAX_SPAN = 48 * HOUR;

        const pad = (n) => String(n).padStart(2, '0');
        const formatLeft = (ms) => {
            const minutes = Math.ceil(ms / 60000);
            return Math.floor(minutes / 60) + ':' + pad(minutes % 60);
        };
        const clamp = (x) => Math.min(1, Math.max(0, x));
        const percent = (x) => (x * 100).toFixed(3) + '%';

        function update() {
            const now = Date.now();
            // Queried on every tick: live refresh adds and removes rows.
            const bars = [...document.querySelectorAll('.event-progress')].map((bar) => ({
                bar,
                start: +bar.dataset.start,
                finish: +bar.dataset.finish,
            }));

            // One scale for the whole page, wide enough to fit the visible outages.
            let span = MIN_SPAN;
            bars.forEach(({start, finish}) => {
                span = Math.max(span, (now - start) / NOW_AT, (finish - now) / (1 - NOW_AT));
            });
            span = Math.min(span, MAX_SPAN);
            const from = now - span * NOW_AT;
            const x = (t) => clamp((t - from) / span);

            bars.forEach(({bar, start, finish}) => {
                const running = now >= start && now < finish;
                const left = x(start);
                const right = x(finish);
                const spanEl = bar.querySelector('.event-progress-span');
                const fill = bar.querySelector('.event-progress-fill');

                spanEl.style.left = percent(left);
                spanEl.style.width = percent(right - left);
                // Cut edges hint that the outage continues beyond the visible window.
                spanEl.classList.toggle('rounded-l', start >= from);
                spanEl.classList.toggle('rounded-r', finish <= from + span);
                fill.style.left = percent(left);
                fill.style.width = percent(Math.max(0, x(Math.min(now, finish)) - left));
                bar.querySelector('.event-progress-marker').style.left = percent(NOW_AT);
                bar.querySelector('.animate-ping').classList.toggle('hidden', !running);
                bar.title = now < start
                    ? 'Начнётся через ' + formatLeft(start - now)
                    : running
                        ? Math.floor((now - start) / (finish - start) * 100) + '% · осталось ' + formatLeft(finish - now)
                        : 'Завершено';
            });
        }

        update();
        setInterval(update, 1000);
    })();
</script>
<script>
    // Refreshes the current events block: finished rows fade out, new ones fade in.
    (function () {
        const root = document.getElementById('live-events');
        if (!root) {
            return;
        }

        const INTERVAL = 60000;
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const duration = (ms) => reduceMotion ? 0 : ms;
        let lastRefresh = Date.now();
        let busy = false;

        function animateIn(row) {
            row.animate(
                [{opacity: 0, transform: 'translateY(-6px)'}, {opacity: 1, transform: 'none'}],
                {duration: duration(500), easing: 'ease-out'}
            );
            row.animate(
                {backgroundColor: ['#cffafe', getComputedStyle(row).backgroundColor]},
                {duration: duration(3000), easing: 'ease-in'}
            );
        }

        async function animateOut(row) {
            row.dataset.leaving = '';
            await row.animate(
                // Fade only: a horizontal shift would overflow the table and flash a scrollbar.
                [{opacity: 1}, {opacity: 0}],
                {duration: duration(500), easing: 'ease-in', fill: 'forwards'}
            ).finished;

            // Table rows can't animate height directly: collapse each cell's content and padding.
            await Promise.all([...row.cells].map((cell) => {
                const wrap = document.createElement('div');
                wrap.style.overflow = 'hidden';
                wrap.append(...cell.childNodes);
                cell.append(wrap);
                const style = getComputedStyle(cell);
                return Promise.all([
                    wrap.animate({height: [wrap.offsetHeight + 'px', '0px']}, {duration: duration(300), fill: 'forwards'}).finished,
                    cell.animate(
                        {paddingTop: [style.paddingTop, '0px'], paddingBottom: [style.paddingBottom, '0px']},
                        {duration: duration(300), fill: 'forwards'}
                    ).finished,
                ]);
            }));
            row.remove();
        }

        function updateRow(row, fresh) {
            row.className = fresh.className;
            [...fresh.cells].forEach((freshCell, i) => {
                const cell = row.cells[i];
                if (!cell) {
                    return;
                }
                // Keep the running progress bar so its marker doesn't restart.
                const bar = cell.querySelector('.event-progress');
                const freshBar = freshCell.querySelector('.event-progress');
                if (bar && freshBar) {
                    Object.assign(bar.dataset, freshBar.dataset);
                    freshBar.replaceWith(bar);
                }
                cell.className = freshCell.className;
                cell.replaceChildren(...freshCell.childNodes);
            });
        }

        function updateSection(section, fresh) {
            const body = section.querySelector('[data-live-rows]');
            const freshBody = fresh.querySelector('[data-live-rows]');
            const freshRows = [...freshBody.rows];
            const freshIds = new Set(freshRows.map((row) => row.id));
            const list = section.querySelector('[data-live-list]');
            const empty = section.querySelector('[data-live-empty]');

            const count = section.querySelector('[data-live-count]');
            if (count && count.textContent !== String(freshRows.length)) {
                count.textContent = freshRows.length;
                count.animate([{color: '#0e7490', transform: 'scale(1.3)'}, {}], {duration: duration(600)});
            }

            if (freshRows.length) {
                section.classList.remove('hidden');
                list?.classList.remove('hidden');
                empty?.classList.add('hidden');
            }

            // Rows are matched within this section: a row moving from "upcoming" to "active"
            // fades out of one table and into the other.
            const current = new Map(
                [...body.rows].filter((row) => !('leaving' in row.dataset)).map((row) => [row.id, row])
            );
            const leaving = [...current.values()].filter((row) => !freshIds.has(row.id)).map(animateOut);
            const added = [];

            let cursor = null;
            freshRows.forEach((freshRow) => {
                let row = current.get(freshRow.id);
                if (row) {
                    updateRow(row, freshRow);
                } else {
                    row = freshRow;
                    added.push(row);
                }
                // Rows still fading out keep their place; don't shuffle around them.
                let ref = cursor ? cursor.nextElementSibling : body.firstElementChild;
                while (ref && 'leaving' in ref.dataset) {
                    ref = ref.nextElementSibling;
                }
                if (ref !== row) {
                    body.insertBefore(row, ref);
                }
                cursor = row;
            });
            added.forEach(animateIn);

            Promise.all(leaving).then(() => {
                if (body.rows.length) {
                    return;
                }
                list?.classList.add('hidden');
                empty?.classList.remove('hidden');
                if ('liveHideEmpty' in section.dataset) {
                    section.classList.add('hidden');
                }
            });
        }

        async function refresh() {
            if (busy) {
                return;
            }
            busy = true;
            lastRefresh = Date.now();
            try {
                const response = await fetch(root.dataset.liveUrl, {headers: {'X-Requested-With': 'XMLHttpRequest'}});
                if (!response.ok) {
                    return;
                }
                const html = await response.text();
                const fresh = new DOMParser().parseFromString(html, 'text/html').getElementById('live-events');
                if (!fresh) {
                    return;
                }
                root.querySelectorAll('[data-live-section]').forEach((section) => {
                    const freshSection = fresh.querySelector(`[data-live-section="${section.dataset.liveSection}"]`);
                    if (freshSection) {
                        updateSection(section, freshSection);
                    }
                });
            } catch (e) {
                // Network hiccup: try again on the next tick.
            } finally {
                busy = false;
            }
        }

        setInterval(() => {
            if (!document.hidden) {
                refresh();
            }
        }, INTERVAL);
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && Date.now() - lastRefresh > INTERVAL) {
                refresh();
            }
        });
    })();
</script>
<!-- Yandex.Metrika counter -->
<script type="text/javascript">
    (function (m, e, t, r, i, k, a) {
        m[i] = m[i] || function () {
            (m[i].a = m[i].a || []).push(arguments);
        };
        m[i].l = 1 * new Date();
        for (var j = 0; j < document.scripts.length; j++) {
            if (document.scripts[j].src === r) {
                return;
            }
        }
        k = e.createElement(t), a = e.getElementsByTagName(t)[0], k.async = 1, k.src = r, a.parentNode.insertBefore(k, a);
    })
    (window, document, 'script', 'https://mc.yandex.ru/metrika/tag.js', 'ym');

    ym(93642618, 'init', {
        clickmap: true,
        trackLinks: true,
        accurateTrackBounce: true,
        webvisor: true,
    });
</script>
<noscript>
    <div><img src="https://mc.yandex.ru/watch/93642618" style="position:absolute; left:-9999px;" alt="" /></div>
</noscript>
<!-- /Yandex.Metrika counter -->
</body>
</html>
