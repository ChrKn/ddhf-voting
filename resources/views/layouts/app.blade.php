<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ isset($title) ? $title.' – ' : '' }}{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-neutral-50 text-neutral-800 antialiased">
    <div class="mx-auto flex min-h-screen max-w-2xl flex-col px-4">
        <header class="pt-6 pb-4 text-center">
            <a href="{{ url('/') }}">
                <img src="{{ asset('img/ddhf-logo.svg') }}" alt="DDHF Logo" class="mx-auto w-56">
            </a>
            <h1 class="mt-5 text-2xl font-bold">{{ $heading ?? 'MV Abstimmungssystem' }}</h1>

            @auth
                <div class="mt-3 flex items-center justify-center gap-3 text-sm text-neutral-600">
                    <span>Angemeldet als <strong>{{ auth()->user()->name }}</strong></span>
                    @if (auth()->user()->canManage())
                        <a href="{{ url('/admin') }}" class="underline hover:text-ddhf">Verwaltung</a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="underline hover:text-ddhf">Abmelden</button>
                    </form>
                </div>
            @endauth
        </header>

        <main class="flex-1 pb-10">
            @if (session('status'))
                <div class="mb-4 rounded border border-green-200 bg-green-50 p-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            {{ $slot }}
        </main>

        @include('partials.footer')
    </div>
    @livewireScripts
</body>
</html>
