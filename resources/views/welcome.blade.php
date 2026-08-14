<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-white text-slate-900 dark:bg-slate-900 dark:text-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>JCA Schedule Extractor</title>

    <x-theme-initializer />

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex h-full flex-col justify-between bg-white font-sans text-slate-900 antialiased transition-colors dark:bg-slate-900 dark:text-slate-100">

    <header class="mx-auto flex w-full max-w-7xl flex-col gap-4 px-6 py-8 sm:flex-row sm:items-center sm:justify-between">
        <div class="space-y-0.5">
            <div class="text-xs font-bold uppercase tracking-widest text-indigo-400">
                Jeppesen Crew Access
            </div>
            <h1 class="text-xl font-extrabold tracking-tight text-slate-900 dark:text-white sm:text-2xl">
                Schedule Extractor
            </h1>
        </div>
        <div class="flex flex-wrap items-center justify-center gap-3 sm:justify-end">
            <x-theme-selector id="welcome-theme-selector" />

            @if (Route::has('login'))
                <nav class="flex gap-4">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="rounded-lg px-4 py-2 text-sm font-semibold text-slate-600 transition hover:text-slate-900 dark:text-slate-300 dark:hover:text-white">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="rounded-lg px-4 py-2 text-sm font-semibold text-slate-600 transition hover:text-slate-900 dark:text-slate-300 dark:hover:text-white">Log in</a>

                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500">Register</a>
                        @endif
                    @endauth
                </nav>
            @endif
        </div>
    </header>

    <main class="grid grid-cols-1 lg:grid-cols-2 max-w-7xl w-full mx-auto px-6 gap-12 items-center my-auto py-12">

        <div class="space-y-8">
            <div class="space-y-4">
                <span class="text-xs font-bold tracking-widest text-indigo-400 uppercase bg-indigo-500/10 px-3 py-1 rounded-full">
                    Schedule Management Simplified
                </span>
                <h1 class="text-4xl font-extrabold leading-tight tracking-tight text-slate-900 dark:text-white sm:text-5xl">
                    Your schedule, <br><span class="text-indigo-400">on your terms.</span>
                </h1>
                <p class="max-w-xl text-lg text-slate-600 dark:text-slate-400">
                    Drop the clunky enterprise portals. Extract your JCA schedule instantly and access it beautifully from anywhere.
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-4">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700/50 dark:bg-slate-800/50">
                    <x-heroicon-o-calendar-days class="h-6 w-6 text-indigo-400 mb-2" />
                    <h3 class="mb-1 text-sm font-semibold text-slate-900 dark:text-white">Understand your schedule</h3>
                    <p class="text-xs text-slate-600 dark:text-slate-400">Clear, readable, and beautifully formatted shifts.</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700/50 dark:bg-slate-800/50">
                    <x-heroicon-o-globe-alt class="h-6 w-6 text-emerald-400 mb-2" />
                    <h3 class="mb-1 text-sm font-semibold text-slate-900 dark:text-white">No VPN required</h3>
                    <p class="text-xs text-slate-600 dark:text-slate-400">Check your upcoming roster securely from your personal calendar app.</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700/50 dark:bg-slate-800/50">
                    <x-heroicon-o-device-tablet class="h-6 w-6 text-amber-400 mb-2" />
                    <h3 class="mb-1 text-sm font-semibold text-slate-900 dark:text-white">Break free from work devices</h3>
                    <p class="text-xs text-slate-600 dark:text-slate-400">Keep up with your life using your personal phone.</p>
                </div>
            </div>

            <div class="flex flex-wrap gap-4 items-center pt-2">
                @auth
                <a href="{{ url('/dashboard') }}" class="rounded-xl bg-indigo-600 px-6 py-3 text-base font-semibold text-white shadow-md transition hover:bg-indigo-500">
                    Go to Dashboard
                </a>
                @else
                <a href="{{ route('register') }}" class="rounded-xl bg-indigo-600 px-6 py-3 text-base font-semibold text-white shadow-md transition hover:bg-indigo-500">
                    Get Started
                </a>
                @endauth
                <a href="#security-notice" class="text-sm text-slate-600 underline underline-offset-4 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-300">
                    Why do I need an account?
                </a>
            </div>
        </div>

        <div class="flex justify-center lg:justify-end items-center">
            <div class="relative mx-auto border-slate-800 bg-slate-800 border-[14px] rounded-[2.5rem] h-[600px] w-[300px] shadow-2xl shadow-indigo-500/10">
                <div class="h-[32px] w-[3px] bg-slate-800 absolute -left-[17px] top-[72px] rounded-l-lg"></div>
                <div class="h-[46px] w-[3px] bg-slate-800 absolute -left-[17px] top-[124px] rounded-l-lg"></div>
                <div class="h-[46px] w-[3px] bg-slate-800 absolute -left-[17px] top-[178px] rounded-l-lg"></div>
                <div class="h-[46px] w-[3px] bg-slate-800 absolute -right-[17px] top-[142px] rounded-r-lg"></div>
                <div class="rounded-[2rem] overflow-hidden w-full h-full bg-slate-950 flex flex-col items-center justify-center p-4 text-center border border-slate-800">

                    <div class="space-y-2 text-slate-600">
                        <img src="{{ asset('images/iphone_screenshot.PNG') }}" alt="App Mockup" class="rounded-lg border border-slate-700/50">
                    </div>

                </div>
            </div>
        </div>
    </main>

    <section id="security-notice" class="border-t border-slate-200 bg-slate-50 py-12 dark:border-slate-800/80 dark:bg-slate-950/50">
        <div class="max-w-4xl mx-auto px-6">
            <div class="flex flex-col items-start gap-6 rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900 sm:p-8 md:flex-row">
                <div class="p-3 bg-amber-500/10 text-amber-400 rounded-xl shrink-0">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m0-6v2m0-8H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-5z" />
                    </svg>
                </div>
                <div class="space-y-3">
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">Why is account registration required?</h2>
                    <p class="text-sm leading-relaxed text-slate-600 dark:text-slate-400">
                        To securely extract and process your schedules, you must upload document files. Registration acts as a critical security measure to prevent unauthorized automated abuse of our file processing servers, keeping the ecosystem safe and efficient for everyone.
                    </p>
                    <div class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600 dark:border-slate-800 dark:bg-slate-950/80 dark:text-slate-400">
                        <span class="font-bold text-amber-500 uppercase tracking-wide text-[10px] bg-amber-500/10 px-1.5 py-0.5 rounded">Security Recommendation</span>
                        <span>Please choose a <strong>unique password</strong> for this application. Do not reuse the password associated with your official work or corporate accounts.</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

<footer class="space-y-1 border-t border-slate-200 py-6 text-center text-xs text-slate-500 dark:border-slate-800/40 dark:text-slate-600">
    <p>&copy; {{ date('Y') }} Crew Compass. All rights reserved.</p>
    <p>This independent tool is not affiliated with or endorsed by Jeppesen, Boeing, or other corporate entity.</p>
    <p>
        <a
            href="mailto:crewcompasscc@gmail.com"
            class="underline hover:text-slate-900 dark:hover:text-slate-300"
        >
            Feedback &amp; Bugs
        </a>
        <span aria-hidden="true">|</span>
        <a
            href="{{ route('privacy.policy') }}"
            class="underline hover:text-slate-900 dark:hover:text-slate-300"
        >
            Privacy Policy
        </a>
    </p>
</footer>

</body>
</html>
