@props(['package', 'seat', 'comparison', 'refresh', 'dirty'])
<dialog id="submitted-bid-comparison" x-ref="comparisonDialog" wire:ignore.self data-block-bid-refresh aria-labelledby="submitted-comparison-title"
    x-data="{ returnTo: null, ...createSubmittedBidComparison(@js($refresh === null ? [] : ['state' => $refresh['state'], 'message' => $refresh['message'], 'finished_at' => $refresh['finished_at'], 'url' => route('crew-bids.submissions.check.status', ['package' => $package, 'seat' => $seat, 'refresh' => $refresh['id']])]), () => $wire.refreshComparison()) }"
    x-effect="if ($wire.showComparison && !$el.open) { returnTo = document.activeElement; $el.showModal() }"
    @keydown.escape.stop @cancel.prevent="$el.close()" @click="if ($event.target === $el) $el.close()" @close="$wire.showComparison = false; if (returnTo?.offsetParent !== null) returnTo?.focus(); else $refs.statusTrigger?.focus()"
    class="fixed inset-0 m-auto max-h-[90dvh] w-[calc(100%_-_1.5rem)] max-w-4xl overflow-y-auto rounded-xl border border-slate-200 bg-white p-0 text-slate-900 shadow-xl backdrop:bg-slate-950/50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
    <div class="space-y-4 p-4 sm:p-6">
        <header class="flex items-start justify-between gap-3">
            <div><h2 id="submitted-comparison-title" class="text-lg font-semibold">Compare with K4</h2><p class="font-mono text-sm text-slate-500 dark:text-slate-400">{{ $package->period }} · B{{ $package->fleet }} · {{ $seat }}</p></div>
            <button type="button" @click="$refs.comparisonDialog.close()" aria-label="Close submitted bid comparison" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg hover:bg-slate-100 focus-visible:ring-2 focus-visible:ring-amber-500 dark:hover:bg-slate-800"><x-heroicon-o-x-mark class="h-5 w-5" aria-hidden="true" /></button>
        </header>
        <p class="text-sm text-slate-600 dark:text-slate-300">This only reads K4. My bid list, preferences and unsaved edits stay in place. A saved check does not prove later portal changes, acceptance time or an awarded line.</p>
        <form wire:ignore method="POST" action="{{ route('crew-bids.submissions.check.store', ['package' => $package, 'seat' => $seat]) }}" @submit.prevent="start($el)" autocomplete="off" data-lpignore="true" class="space-y-3 rounded-xl border border-slate-200 p-3 dark:border-slate-700">
            @csrf
            <x-crew-bids.portal-credentials viewing id-prefix="compare-k4" />
            <button type="submit" :disabled="busy || ['queued', 'running'].includes(state)" class="inline-flex min-h-11 items-center gap-2 rounded-lg bg-[#1B365D] px-4 text-sm font-semibold text-white focus-visible:ring-2 focus-visible:ring-amber-500 disabled:opacity-50"><x-heroicon-o-arrow-path class="h-4 w-4" aria-hidden="true" /><span x-text="busy || ['queued', 'running'].includes(state) ? 'Checking K4…' : 'Refresh from K4'">Refresh from K4</span></button>
        </form>
        <div role="status" aria-live="polite" class="space-y-1 text-sm">
            <p x-text="message"></p>
            <p x-show="finishedAt" class="font-mono text-xs text-slate-500 dark:text-slate-400" x-text="finishedAt"></p>
            <p x-cloak x-show="connectionError" class="text-amber-800 dark:text-amber-300">Connection interrupted. The last check remains below. The last check stays visible. <button type="button" @click="check()" class="min-h-11 font-semibold underline focus-visible:ring-2 focus-visible:ring-amber-500">Check progress</button></p>
        </div>
        @if ($comparison)
            <section class="space-y-3" aria-label="Complete bid order comparison">
                <div class="grid gap-3 text-sm sm:grid-cols-2">
                    <div><h3 class="font-semibold">My bid list</h3><p x-show="$wire.dirty || $wire.$dirty(['goals', 'daysOff', 'startCity'])" class="text-amber-800 dark:text-amber-300">Includes unsaved changes</p></div>
                    <div><h3 class="font-semibold">K4 submitted bids</h3><p class="text-xs text-slate-500 dark:text-slate-400">@if ($comparison->checkedAt)Last checked <time datetime="{{ $comparison->checkedAt->toIso8601String() }}">{{ $comparison->checkedAt->utc()->format('Y-m-d H:i:s') }} UTC</time>@else Not checked @endif</p></div>
                </div>
                @if ($comparison->reason)<p class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-400/10 dark:text-amber-200">{{ $comparison->reason }}. @if ($comparison->hasCheck)The last check is historical; refresh before using it as the current submitted order.@else Refresh from K4 to establish a submitted order.@endif</p>@endif
                @if ($comparison->hasCheck)
                    <p class="text-sm">{{ $comparison->added }} only in My bid list · {{ $comparison->removed }} only in K4 · Retained choices: {{ $comparison->reordered ? 'reordered' : 'same relative order' }}</p>
                @endif
                <p class="text-xs text-slate-500 dark:text-slate-400">All choices are included, regardless of workspace filters or line type. “Moved” compares numeric ranks; additions can shift ranks without reordering retained choices.</p>
                <div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
                    <table class="w-full text-start text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-800"><tr><th scope="col" class="p-3 text-start">Line</th><th scope="col" class="p-3 text-start">My rank</th><th scope="col" class="p-3 text-start">K4 rank</th><th scope="col" class="p-3 text-start">Comparison</th></tr></thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            @forelse ($comparison->rows as $row)
                                <tr><th scope="row" class="p-3 text-start font-mono">{{ $row['label'] }}</th><td class="p-3 font-mono">{{ $row['local_rank'] ?? '—' }}</td><td class="p-3 font-mono">{{ $row['submitted_rank'] ?? '—' }}</td><td class="p-3">{{ $row['status'] }}@if ($row['annotation'])<span class="block text-xs text-amber-800 dark:text-amber-300">{{ $row['annotation'] }}</span>@endif</td></tr>
                            @empty
                                <tr><td colspan="4" class="p-4 text-slate-500 dark:text-slate-400">My bid list is empty. Refresh from K4 to compare its submitted order.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
</dialog>
