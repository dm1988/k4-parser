<x-app-layout>
    <div class="mx-auto flex max-w-5xl flex-col gap-5 px-4 py-6 sm:px-6">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-bold text-[#1B365D] dark:text-slate-100">Saved delay report</h1>
            <a href="{{ route('flight-delays.index') }}" wire:navigate class="rounded text-sm font-semibold text-[#1B365D] underline underline-offset-4 focus-visible:ring-2 focus-visible:ring-[#C5A059] dark:text-slate-200">Delay history</a>
        </header>
        <p class="text-sm text-[#4A5568] dark:text-slate-300">Read-only history · Last saved {{ $savedAt }} UTC</p>
        <dl class="grid grid-cols-2 gap-3 rounded-xl bg-white p-4 dark:bg-slate-900 sm:grid-cols-3">
            @foreach ($flightDetails as $label => $value)
                <div>
                    <dt class="text-xs font-semibold text-[#4A5568] dark:text-slate-400">{{ $label }}</dt>
                    <dd class="break-words font-mono text-sm text-[#0B0E14] dark:text-slate-100">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
        <x-flight-release.delay-report :text="$reportText" />
    </div>
</x-app-layout>
