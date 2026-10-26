<x-app-layout>
    <div class="mx-auto flex max-w-5xl flex-col gap-5 px-4 py-6 sm:px-6">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-bold text-[#1B365D] dark:text-slate-100">Delay history</h1>
            <a href="{{ route('flight-release.index') }}" wire:navigate class="rounded text-sm font-semibold text-[#1B365D] underline underline-offset-4 focus-visible:ring-2 focus-visible:ring-[#C5A059] dark:text-slate-200">Flight Plan Brief</a>
        </header>
        <p class="text-sm text-[#4A5568] dark:text-slate-300">Saved observations remain available after clearing or replacing a flight plan. All times are UTC.</p>
        <ul class="flex flex-col gap-3">
            @forelse ($delays as $delay)
                <li>
                    <a href="{{ route('flight-delays.show', $delay) }}" wire:navigate class="flex flex-col gap-2 rounded-xl border border-[#1B365D]/15 bg-white p-4 focus-visible:ring-2 focus-visible:ring-[#C5A059] dark:border-slate-700 dark:bg-slate-900">
                        <span class="font-mono font-bold text-[#1B365D] dark:text-slate-100">{{ $summaries[$delay->id]['Flight'] }} · {{ $summaries[$delay->id]['Flight date'] }}</span>
                        <span class="font-mono text-sm text-[#4A5568] dark:text-slate-300">{{ $summaries[$delay->id]['Departure'] }} → {{ $summaries[$delay->id]['Destination'] }} · {{ $summaries[$delay->id]['Tail'] }}</span>
                        <span class="text-xs text-[#4A5568] dark:text-slate-400">Saved {{ $summaries[$delay->id]['Saved at (UTC)'] }} · View report</span>
                    </a>
                </li>
            @empty
                <li class="rounded-xl bg-white p-5 text-[#4A5568] dark:bg-slate-900 dark:text-slate-300">No delay reports recorded yet. Open Delay reporting from a loaded flight plan to record observations.</li>
            @endforelse
        </ul>
        {{ $delays->links() }}
    </div>
</x-app-layout>
