<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-zinc-50 font-sans text-zinc-900 antialiased">
        <div class="flex min-h-screen flex-col items-center justify-center px-4 py-10">
            <a href="/" wire:navigate class="mb-6 flex items-center gap-3">
                <span class="flex size-11 items-center justify-center rounded-xl bg-indigo-600 text-lg font-bold text-white">HR</span>
                <span class="text-xl font-semibold">{{ config('app.name') }}</span>
            </a>

            <div class="w-full max-w-md rounded-xl border border-zinc-200 bg-white p-6 shadow-xs">
                {{ $slot }}
            </div>
        </div>
        @fluxScripts
    </body>
</html>
