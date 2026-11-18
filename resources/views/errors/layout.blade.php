<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') — El Rey</title>
    <style>
        body { margin: 0; background: #eef3f0; color: #1b2320; font: 20px/1.5 system-ui, sans-serif; }
        main { max-width: 620px; margin: 12vh auto; padding: 24px; }
        h1 { font-size: 30px; line-height: 1.2; }
        a { display: inline-block; margin-top: 16px; padding: 16px 24px; border-radius: 8px; background: #14532d; color: white; text-decoration: none; font-weight: bold; }
        a:focus-visible { outline: 4px solid #f5b700; outline-offset: 4px; }
    </style>
</head>
<body>
<main>
    <p>Frutas y Verduras El Rey</p>
    <h1>@yield('title')</h1>
    <p>@yield('message')</p>
    <a href="{{ route('home') }}">⌂ Volver al inicio</a>
</main>
</body>
</html>
