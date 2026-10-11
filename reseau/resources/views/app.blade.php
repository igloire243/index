<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Réseau</title>
    <meta name="description" content="Mon carnet de contacts, ma santé et mes habitudes.">
    <meta name="theme-color" content="#0f172a">
    <meta name="robots" content="noindex, nofollow">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/icon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/icon-192.png">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
    <div id="app"></div>
    <noscript>Cette application nécessite JavaScript.</noscript>
</body>
</html>
