<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="referrer" content="no-referrer">
    <title>{{ $title ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-white text-neutral-900 antialiased">
    <div class="mx-auto flex min-h-screen max-w-5xl flex-col px-8 py-8">
        <header class="flex items-center justify-between gap-6 border-b border-neutral-200 pb-4">
            <img src="{{ asset('img/ddhf-logo.jpg') }}" alt="DDHF Logo" class="w-48">
            <div class="text-right text-xl font-semibold text-neutral-600">{{ $title ?? '' }}</div>
        </header>
        <main class="flex-1 pt-8">
            {{ $slot }}
        </main>
    </div>
</body>
</html>
