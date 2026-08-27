@props(['model'])

<div {{ $attributes->merge(['class' => 'flex min-w-0 flex-col gap-5 p-3 sm:p-5']) }}>
    <section aria-labelledby="review-mel-cdl-items-heading" class="flex min-w-0 flex-col gap-3">
        <div class="flex flex-wrap items-end justify-between gap-2">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#4A5568] dark:text-slate-400">Maintenance review</p>
                <h3 id="review-mel-cdl-items-heading" class="text-base font-bold text-[#1B365D] dark:text-slate-100">Source-listed items</h3>
            </div>
            <p class="font-mono text-xs font-semibold tabular-nums text-[#4A5568] dark:text-slate-400">{{ $model->maintenanceItemCountLabel() }}</p>
        </div>

        <x-flight-release.maintenance-items
            :items="$model->maintenanceItems()"
            id-prefix="review-maintenance-item"
        />
    </section>

    <x-flight-release.maintenance-caution />

    <x-flight-release.source-evidence message="Maintenance source fragments and control evidence remain private to this extraction result and are not included in the Livewire snapshot." />
</div>
