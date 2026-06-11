<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-900 text-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>K4 Schedule Parser</title>

    <preconnect href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>
<body class="antialiased font-sans h-full flex flex-col justify-between">

    <header class="max-w-7xl w-full mx-auto px-6 py-6 flex justify-between items-center">
        <div class="text-xl font-bold tracking-wider text-indigo-400">
            K4 SCHEDULE PARSER
        </div>
        <div>
            @if (Route::has('login'))
                <nav class="flex gap-4">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="rounded-lg px-4 py-2 text-sm font-semibold text-slate-300 hover:text-white transition">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="rounded-lg px-4 py-2 text-sm font-semibold text-slate-300 hover:text-white transition">Log in</a>

                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500 transition">Register</a>
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
                <h1 class="text-4xl sm:text-5xl font-extrabold tracking-tight text-white leading-tight">
                    Your schedule, <br><span class="text-indigo-400">on your terms.</span>
                </h1>
                <p class="text-lg text-slate-400 max-w-xl">
                    Drop the clunky enterprise portals. Parse your K4 schedule instantly and access it beautifully from anywhere.
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-4">
                <div class="p-4 bg-slate-800/50 rounded-xl border border-slate-700/50">
                    <x-heroicon-o-calendar-days class="h-6 w-6 text-indigo-400 mb-2" />
                    {{-- <div class="text-indigo-400 font-bold text-lg mb-1">01</div> --}}
                    <h3 class="text-sm font-semibold text-white mb-1">Understand your schedule</h3>
                    <p class="text-xs text-slate-400">Clear, readable, and beautifully formatted shifts.</p>
                </div>
                <div class="p-4 bg-slate-800/50 rounded-xl border border-slate-700/50">
                    <x-heroicon-o-globe-alt class="h-6 w-6 text-emerald-400 mb-2" />
                    {{-- <div class="text-emerald-400 font-bold text-lg mb-1">02</div> --}}
                    <h3 class="text-sm font-semibold text-white mb-1">No VPN required</h3>
                    <p class="text-xs text-slate-400">Check your upcoming calendar securely from the open web.</p>
                </div>
                <div class="p-4 bg-slate-800/50 rounded-xl border border-slate-700/50">
                    <x-heroicon-o-device-tablet class="h-6 w-6 text-amber-400 mb-2" />
                    {{-- <div class="text-amber-400 font-bold text-lg mb-1">03</div> --}}
                    <h3 class="text-sm font-semibold text-white mb-1">Break free from work devices</h3>
                    <p class="text-xs text-slate-400">Keep up with your life using your personal phone.</p>
                </div>
            </div>

            <div class="flex flex-wrap gap-4 items-center pt-2">
                <a href="{{ route('register') }}" class="rounded-xl bg-indigo-600 px-6 py-3 text-md font-semibold text-white shadow-md hover:bg-indigo-500 transition">
                    Get Started Free
                </a>
                <a href="#security-notice" class="text-sm text-slate-400 hover:text-slate-300 underline underline-offset-4">
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

                    <div class="text-slate-600 space-y-2">
                        {{-- <svg class="mx-auto h-12 w-12 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.5 1.5H13.5M10.5 22.5H13.5M19.5 4.5V19.5C19.5 20.6046 18.6046 21.5 17.5 21.5H6.5C5.39543 21.5 4.5 20.6046 4.5 19.5V4.5C4.5 3.39543 5.39543 2.5 6.5 2.5H17.5C18.6046 2.5 19.5 3.39543 19.5 4.5Z" />
                        </svg> --}}
                        {{-- <span class="block text-xs uppercase tracking-widest font-semibold text-slate-500">App Mockup</span> --}}
                        <img src="{{ asset('images/iphone_screenshot.PNG') }}" alt="App Mockup" class="rounded-lg border border-slate-700/50">
                    </div>

                </div>
            </div>
        </div>
    </main>

    <section id="security-notice" class="border-t border-slate-800/80 bg-slate-950/50 py-12">
        <div class="max-w-4xl mx-auto px-6">
            <div class="bg-slate-900 border border-slate-800 p-6 sm:p-8 rounded-2xl flex flex-col md:flex-row gap-6 items-start">
                <div class="p-3 bg-amber-500/10 text-amber-400 rounded-xl shrink-0">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m0-6v2m0-8H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-5z" />
                    </svg>
                </div>
                <div class="space-y-3">
                    <h2 class="text-lg font-bold text-white">Why is account registration required?</h2>
                    <p class="text-sm text-slate-400 leading-relaxed">
                        To securely parse and store your schedules, you must upload document files. Registration acts as a critical security measure to prevent unauthorized automated abuse of our file processing servers, keeping the ecosystem safe and efficient for everyone.
                    </p>
                    <div class="p-3 bg-slate-950/80 rounded-lg border border-slate-800 text-xs text-slate-400 flex items-center gap-2">
                        <span class="font-bold text-amber-500 uppercase tracking-wide text-[10px] bg-amber-500/10 px-1.5 py-0.5 rounded">Security Recommendation</span>
                        <span>Please choose a <strong>unique password</strong> for this application. Do not reuse the password associated with your official work or corporate accounts.</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer class="border-t border-slate-800/40 py-6 text-center text-xs text-slate-600">
        &copy; {{ date('Y') }} Crew Compass. All rights reserved. Not affiliated with corporate entity systems.
    </footer>

</body>
</html>
