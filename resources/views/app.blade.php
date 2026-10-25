<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <script>
            (function () {
                var appearance = 'system';
                try { appearance = localStorage.getItem('appearance') || 'system'; } catch (e) {}
                var dark = appearance === 'dark' || (appearance === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.classList.toggle('dark', dark);
            })();
        </script>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        <meta name="description" content="Free, open-source DORA and PR metrics for your GitHub repos. Run it yourself with one token; your data never leaves your server.">
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ config('app.name', 'Houston') }}">
        <meta property="og:title" content="{{ config('app.name', 'Houston') }} · Mission control for your engineering team">
        <meta property="og:description" content="Free, open-source DORA and PR metrics for your GitHub repos. Run it yourself with one token; your data never leaves your server.">
        <meta property="og:image" content="{{ asset('landing/og.png') }}">
        <meta property="og:image:alt" content="Houston logo with the headline Mission control for your engineering team, next to a repository report showing time to merge, pickup time and where the time goes">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:image" content="{{ asset('landing/og.png') }}">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
