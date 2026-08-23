@props(['model'])

<div {{ $attributes->merge(['class' => 'flex min-w-0 flex-col gap-5 p-3 sm:p-5']) }}>
    <section aria-labelledby="fuel-score-summary-heading" class="flex min-w-0 flex-col gap-3">
        <div class="flex flex-wrap items-end justify-between gap-2">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#4A5568] dark:text-slate-400">Confirmed release fields</p>
                <h3 id="fuel-score-summary-heading" class="text-base font-bold text-[#1B365D] dark:text-slate-100">Fuel summary</h3>
            </div>
            <p class="text-xs font-semibold text-[#4A5568] dark:text-slate-400">No score or status inferred</p>
        </div>

        <dl class="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($model->fuelScoreFields() as $field)
                <div class="flex min-w-0 flex-col gap-1 rounded-lg border border-[#1B365D]/10 bg-white px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900">
                    <dt class="text-[9px] font-normal uppercase tracking-[0.16em] text-[#4A5568] dark:text-slate-400">
                        {{ $field['label'] }}
                    </dt>
                    <dd class="flex flex-wrap items-baseline gap-1 break-words font-mono leading-tight text-[#0B0E14] dark:text-slate-100">
                        <span class="text-xl font-bold">{{ $field['value'] ?? 'Not present in this release' }}</span>
                        @if ($field['unit'])
                            <span class="text-xs font-normal text-[#4A5568]/70 dark:text-slate-500">{{ $field['unit'] }}</span>
                        @endif
                    </dd>
                </div>
            @endforeach
        </dl>
    </section>

    <aside class="rounded-lg border border-amber-300/70 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-400/25 dark:bg-amber-400/10 dark:text-amber-100">
        <div class="flex items-start gap-3">
            <x-heroicon-o-exclamation-triangle class="h-5 w-5 shrink-0" />
            <div class="flex min-w-0 flex-col gap-1">
                <p class="font-bold">Source values only</p>
                <p class="leading-5">This view repeats confirmed release fuel quantities. It does not calculate a fuel score, determine compliance, or assess dispatchability.</p>
            </div>
        </div>
    </aside>

    <x-flight-release.source-evidence message="Fuel summary and waypoint source evidence remain private to this extraction result and are not included in the Livewire snapshot." />
</div>
