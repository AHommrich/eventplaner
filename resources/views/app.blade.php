<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- System-Darkmode früh anwenden --}}
    <script>
        (function () {
            const appearance = '{{ $appearance ?? "system" }}';
            if (appearance === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>

    <style>
        html { background-color: oklch(1 0 0); }
        html.dark { background-color: oklch(0.145 0 0); }
    </style>

    {{-- PWA: Name/Titel (Inertia) --}}
    <title inertia>{{ config('app.name', 'eveplan') }}</title>
    <meta name="application-name" content="eveplan">
    <meta name="apple-mobile-web-app-title" content="eve">

    {{-- PWA: Manifest + Theme-Farbe (Cache-Buster am Manifest!) --}}
    <link rel="manifest" href="/manifest.webmanifest?v=6">
    <meta name="theme-color" content="#111827">

    {{-- PWA: iOS Vollbild & Statusbar --}}
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="mobile-web-app-capable" content="yes">

    {{-- Favicon --}}
    <link rel="icon" href="/logo-admin.png" type="image/png">

    {{-- PWA: App-Icons (PNG, wichtig für Install) --}}
    <link rel="apple-touch-icon" href="/icons/icon-192.png">
    {{-- optional weitere Größen --}}
    <link rel="apple-touch-icon" sizes="180x180" href="/icons/icon-192.png">
    <link rel="apple-touch-icon" sizes="512x512" href="/icons/icon-512.png">

    {{-- (Optional) Maskable icon hint – Android/Chrome nutzt purpose:maskable aus dem Manifest --}}
    <link rel="mask-icon" href="/icons/maskable-512.png" color="#111827">

    {{-- Fonts werden self-hosted via app.css geladen (kein externer Drittanbieter) --}}

    @routes
    @vite(['resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
    @inertiaHead
</head>
<body class="font-sans antialiased">
    @inertia
</body>
</html>
