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
        </nav>
    </header>
    @yield('content')
</div>
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
