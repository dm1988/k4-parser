<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-white text-slate-900 dark:bg-slate-900 dark:text-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="K4 Extractor by Crew Compass turns crew schedules and flight plan documents into organized, reviewable information. Explore Schedule Extractor and Flight Plan Extractor.">
    <title>K4 Extractor | Crew Compass</title>
    <x-theme-initializer />
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="cc-welcome flex min-h-full flex-col bg-[#F8F9FA] font-sans text-[#0B0E14] antialiased dark:bg-slate-950 dark:text-slate-100">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-white focus:p-4 focus:text-[#1B365D]">Skip to content</a>

    <header class="border-b border-[#1B365D]/15 bg-white dark:border-slate-800 dark:bg-slate-900">
        <div class="mx-auto flex max-w-7xl flex-col gap-6 px-6 py-6 lg:flex-row lg:items-center lg:justify-between">
            <a href="{{ route('welcome') }}" class="flex w-fit items-center gap-3 rounded-md" aria-label="Crew Compass — K4 Extractor home">
                <img src="{{ asset('images/cc_logo_512px.png') }}" alt="" width="56" height="56" class="h-14 w-14 object-contain">
                <span class="flex flex-col gap-1">
                    <span class="text-sm font-bold uppercase tracking-widest text-[#1B365D] dark:text-[#E8D2A5]">Crew Compass</span>
                    <span class="text-xl font-bold tracking-tight">K4 Extractor</span>
                </span>
            </a>
            <div class="flex flex-wrap items-center gap-4">
                <nav aria-label="Main navigation" class="flex flex-wrap items-center gap-4 text-sm font-semibold">
                    <a href="#extractors" class="cc-welcome-nav-link">Explore tools</a>
                    @auth
                        <a href="{{ route('dashboard') }}" class="cc-welcome-nav-link">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="cc-welcome-nav-link">Log in</a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="cc-btn-primary">Register</a>
                        @endif
                    @endauth
                </nav>
                <x-theme-selector id="welcome-theme-selector" />
            </div>
        </div>
    </header>

    <main id="main-content" tabindex="-1" class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-12 px-6 py-12 sm:gap-16 sm:py-16">
        <section aria-labelledby="welcome-title" class="flex max-w-3xl flex-col items-start gap-6">
            <span class="cc-badge">Your documents. A clearer view.</span>
            <h1 id="welcome-title" class="text-4xl font-bold leading-tight tracking-tight text-[#1B365D] dark:text-slate-100 sm:text-5xl">Turn Crew Documents into Actionable Flight Data</h1>
            <p class="max-w-2xl text-lg leading-relaxed text-[#4A5568] dark:text-slate-300">From your next roster to your next flight, Crew Compass brings the details together. Choose a tool to extract, organize, and review the information in your documents.</p>
        </section>

        <section id="extractors" aria-label="Extraction tools" class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <x-feature-card
                id="schedule-extractor"
                title="Schedule Extractor"
                description="Turn your Jeppesen Crew Access schedule into a readable roster, with flights, duties, and layovers in one place."
                :action="$scheduleAction"
                :primary="true"
            >
                <x-slot:icon><x-heroicon-o-calendar-days class="h-6 w-6" /></x-slot:icon>
                <ul class="flex flex-col gap-3" role="list">
                    <li>Review your upcoming flights and time away.</li>
                    <li>Find the details of each duty and layover.</li>
                    <li>Export events to your personal calendar.</li>
                </ul>
            </x-feature-card>

            <x-feature-card
                id="flight-plan-extractor"
                title="Flight Plan Extractor"
                description="Turn a supported flight release into a Flight Plan Brief, with extracted planning details organized for review."
                :action="$flightPlanAction"
                :demo="true"
            >
                <x-slot:icon><x-heroicon-o-paper-airplane class="h-6 w-6" /></x-slot:icon>
                <ul class="flex flex-col gap-3" role="list">
                    <li>Review the flight route and operational overview.</li>
                    <li>Explore available fuel, weather, and weight &amp; balance details.</li>
                    <li>Check extracted information against the source release.</li>
                </ul>
            </x-feature-card>
        </section>

        <section id="security-notice" aria-labelledby="security-title" class="rounded-lg border border-[#1B365D]/20 bg-white p-6 dark:border-slate-700 dark:bg-slate-900 sm:p-8">
            <div class="flex items-center gap-3">
                <x-heroicon-o-shield-check class="h-7 w-7 shrink-0 text-[#1B365D] dark:text-[#E8D2A5]" aria-hidden="true" />
                <h2 id="security-title" class="text-xl font-bold">Data Security &amp; Privacy</h2>
            </div>
            <p class="mt-3 text-sm leading-relaxed text-[#4A5568] dark:text-slate-300">Your account helps protect document access; sign-in rate limits help prevent automated abuse.</p>
            <div class="mt-6 grid gap-6 md:grid-cols-3">
                <div class="flex items-start gap-3">
                    <x-heroicon-o-lock-closed class="h-5 w-5 shrink-0 text-[#1B365D] dark:text-[#E8D2A5]" aria-hidden="true" />
                    <div class="space-y-2">
                        <h3 class="text-sm font-bold">Private upload storage</h3>
                        <p class="text-sm leading-relaxed text-[#4A5568] dark:text-slate-300">Uploaded documents are stored privately, outside the public file directory.</p>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <x-heroicon-o-key class="h-5 w-5 shrink-0 text-[#1B365D] dark:text-[#E8D2A5]" aria-hidden="true" />
                    <div class="space-y-2">
                        <h3 class="text-sm font-bold">Use a unique password.</h3>
                        <p class="text-sm leading-relaxed text-[#4A5568] dark:text-slate-300">Do not reuse the password associated with your official work or corporate accounts.</p>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <x-heroicon-o-document-text class="h-5 w-5 shrink-0 text-[#1B365D] dark:text-[#E8D2A5]" aria-hidden="true" />
                    <div class="space-y-2">
                        <h3 class="text-sm font-bold">Know how your data is used</h3>
                        <p class="text-sm leading-relaxed text-[#4A5568] dark:text-slate-300">Read about data collection, retention, and your choices in our <a href="{{ route('privacy.policy') }}" class="rounded-sm font-semibold underline underline-offset-4">Privacy Policy</a>.</p>
                    </div>
                </div>
            </div>
            <p class="mt-6 text-xs text-[#4A5568] dark:text-slate-400">Tool availability depends on your account and which features are enabled.</p>
        </section>

        <section aria-labelledby="schedule-preview-title" class="cc-card grid items-center gap-8 p-6 sm:p-8 md:grid-cols-2">
            <div class="flex flex-col gap-4">
                <p class="text-xs font-bold uppercase tracking-widest text-[#1B365D] dark:text-[#E8D2A5]">Inside Schedule Extractor</p>
                <h2 id="schedule-preview-title" class="text-2xl font-bold">Your roster, ready for everyday life.</h2>
                <p class="leading-relaxed text-[#4A5568] dark:text-slate-300">Review the schedule you upload, then bring the events you need into your personal calendar. Keep your next flight and layover close at hand.</p>
            </div>
            <figure class="flex min-w-0 flex-col items-center gap-4">
                <img src="{{ asset('images/iphone_screenshot.PNG') }}" alt="Schedule Extractor on mobile with an upload area for roster screenshots or a trip PDF" width="1290" height="2655" loading="lazy" class="h-auto w-full max-w-[240px] rounded-2xl border border-[#1B365D]/15 shadow-sm dark:border-slate-700">
                <figcaption class="text-center text-xs text-[#4A5568] dark:text-slate-400">A closer look at Schedule Extractor on mobile.</figcaption>
            </figure>
        </section>


    </main>

    <footer class="border-t border-[#1B365D]/15 bg-white px-6 py-8 dark:border-slate-800 dark:bg-slate-900">
        <div class="mx-auto flex max-w-7xl flex-col gap-3 text-sm text-[#4A5568] dark:text-slate-300">
            <p>&copy; {{ date('Y') }} Crew Compass. All rights reserved.</p>
            <p>This independent tool is not affiliated with or endorsed by Jeppesen, Boeing, or other corporate entity.</p>
            <nav aria-label="Footer navigation" class="flex flex-wrap gap-6">
                <a href="mailto:crewcompasscc@gmail.com" class="rounded-sm underline underline-offset-4">Feedback &amp; Bugs</a>
                <a href="{{ route('privacy.policy') }}" class="rounded-sm underline underline-offset-4">Privacy Policy</a>
            </nav>
        </div>
    </footer>
</body>
</html>
