<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold">Sync from K4</h1></x-slot>
    <section class="mx-auto my-6 max-w-2xl space-y-5 rounded-xl bg-white p-5 shadow dark:bg-slate-900">
        <a href="{{ route('crew-bids.index') }}" class="inline-flex min-h-11 items-center underline focus-visible:ring-2 focus-visible:ring-amber-500">Back to K4 Bids</a>
        <p class="font-mono">{{ $package->period }} · B{{ $package->fleet }} · {{ $seat }}</p>
        <p class="text-sm">Read your submitted bids from K4 and save them to My bid list in the same order. This does not submit or change bids on K4.</p>
        @if (session('crew_bid_import_error'))<p role="alert" class="rounded-lg bg-amber-50 p-3 text-amber-900 dark:bg-amber-950 dark:text-amber-200">{{ session('crew_bid_import_error') }}</p>@endif
        <form method="POST" action="{{ route('crew-bids.submissions.sync.store', ['package' => $package, 'seat' => $seat]) }}" autocomplete="off" x-data="{ busy: false }" @submit="busy = true" class="space-y-4">
            @csrf
            <input type="hidden" name="revision" value="{{ $workspace->revision ?? 0 }}">
            @if (($workspace->selections ?? []) !== [])
                <div class="space-y-3 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">
                    <p>Syncing will overwrite all {{ count($workspace->selections) }} choices in My bid list with your submitted K4 bids.</p>
                    <label class="flex min-h-11 items-center gap-3"><input type="checkbox" name="overwrite" value="1" required class="rounded border-amber-500 focus:ring-amber-500">Replace my existing bid list</label>
                </div>
            @else
                <p class="text-sm text-slate-600 dark:text-slate-300">My bid list is empty. Your submitted K4 bids will be added here.</p>
            @endif
            <x-crew-bids.portal-credentials syncing />
            <button type="submit" :disabled="busy" class="inline-flex min-h-11 items-center gap-2 rounded-lg bg-[#1B365D] px-4 font-semibold text-white focus-visible:ring-2 focus-visible:ring-amber-500 disabled:opacity-50"><x-heroicon-o-arrow-down-tray class="h-5 w-5 shrink-0" aria-hidden="true" /><span x-show="!busy">Sync from K4</span><span x-show="busy" x-cloak role="status">Reading submitted bids…</span></button>
        </form>
    </section>
</x-app-layout>
