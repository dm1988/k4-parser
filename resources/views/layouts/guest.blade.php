<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <x-theme-initializer />

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="font-sans text-gray-900 antialiased dark:text-gray-100">
        <div class="relative flex min-h-screen flex-col items-center bg-gray-100 pt-16 transition-colors dark:bg-gray-950 sm:justify-center sm:pt-0">
            <x-theme-selector id="guest-theme-selector" class="absolute end-4 top-4 sm:end-6 sm:top-6" />

            <div>
                <a href="/">
                    <x-application-logo class="h-20 w-20 fill-current text-gray-500 dark:text-gray-400" />
                </a>
            </div>

            <div class="mt-6 w-full overflow-hidden bg-white px-6 py-4 shadow-md dark:bg-gray-900 dark:shadow-black/30 sm:max-w-md sm:rounded-lg">
                {{ $slot }}
            </div>
        </div>

        @livewireScripts
    </body>
</html>
