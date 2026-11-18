<!DOCTYPE html>
<html lang="es">
    <head>
        <link rel="icon" type="image/svg+xml" href="/favicon.svg">
        <meta charset="utf-8">
        <link rel="icon" href="/favicon.ico" sizes="any">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#2D7A3E">
        <title inertia>{{ config('app.name', 'El Rey') }}</title>
        {{-- Todo local: sin CDNs (el sistema funciona sin internet). --}}
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body>
        @inertia
    </body>
</html>
