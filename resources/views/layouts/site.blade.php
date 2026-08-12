<!DOCTYPE html>
<html lang="en-ZA">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title', 'Dynamic Solutions — Bulk diesel supply, Benoni')</title>
    <meta name="description" content="@yield('description', 'Bulk diesel supply and secure truck parking at 31 Dassie Road, Zesfontein, Benoni. 50ppm and 500ppm delivered across Gauteng.')">
    <link rel="canonical" href="{{ url()->current() }}">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Dynamic Solutions">
    <meta property="og:title" content="@yield('title', 'Dynamic Solutions — Bulk diesel supply, Benoni')">
    <meta property="og:description" content="@yield('description', 'Bulk diesel supply and secure truck parking in Benoni, Gauteng.')">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ asset('images/og-default.png') }}">
    <meta name="twitter:card" content="summary_large_image">

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">

    {{-- Self-hosted, so a blocked font request cannot delay first paint. --}}
    <link rel="preload" href="/fonts/archivo-black-400-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/barlow-400-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/jetbrains-mono-700-latin.woff2" as="font" type="font/woff2" crossorigin>

    @vite(['resources/css/app.css', 'resources/js/site.ts'])

    <script type="application/ld+json">@json($jsonLd ?? \App\Support\StructuredData::localBusiness(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)</script>

    @stack('head')
</head>
<body class="font-body bg-white text-ds-ink antialiased">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:m-3 focus:bg-ds-ink focus:px-4 focus:py-2 focus:text-white">
        Skip to content
    </a>

    <div class="ds-shell">
        @include('partials.site-nav')

        <main id="main">
            @yield('content')
        </main>

        @include('partials.site-footer')
    </div>
</body>
</html>
