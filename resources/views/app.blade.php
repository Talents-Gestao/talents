<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="#632a7e">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <link rel="icon" href="/pwa-icon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/pwa-icon.svg">
        <link rel="manifest" href="{{ asset('build/manifest.webmanifest') }}">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <meta name="description" content="Talents — Consultoria estratégica em gestão de pessoas. Comportamento, liderança, cultura organizacional e desenvolvimento humano.">
        <meta property="og:site_name" content="Talents Gestão de Pessoas">
        <meta property="og:type" content="website">
        <meta property="og:image" content="{{ rtrim(config('app.url'), '/') }}/images/logo.png">
        <meta name="twitter:card" content="summary_large_image">

        <!-- Fonts (Figtree local; ver @font-face em resources/css/app.css) -->
        <link rel="preload" href="{{ Vite::asset('resources/fonts/figtree-latin-400-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
        @if (($page['component'] ?? null) === 'Welcome')
            {{-- Hero da Home: o leiaute mede com Figtree 500/600 e a foto é o LCP; baixam já com o HTML. --}}
            <link rel="preload" href="{{ Vite::asset('resources/fonts/figtree-latin-500-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
            <link rel="preload" href="{{ Vite::asset('resources/fonts/figtree-latin-600-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
            <link rel="preload" href="/images/hero-home.webp" as="image" type="image/webp" fetchpriority="high">
        @endif

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
