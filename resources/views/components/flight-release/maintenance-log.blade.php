@props(['model'])

<div {{ $attributes->merge(['class' => 'flex min-w-0 flex-col gap-5 p-3 sm:p-5']) }}>
    <section aria-labelledby="maintenance-flight-context-heading" class="flex min-w-0 flex-col gap-3">
        <div class="flex flex-wrap items-end justify-between gap-2">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#4A5568] dark:text-slate-400">Log sheet context</p>
                <h3 id="maintenance-flight-context-heading" class="text-base font-bold text-[#1B365D] dark:text-slate-100">Flight details</h3>
            </div>
            <p class="text-xs font-semibold text-[#4A5568] dark:text-slate-400">Confirmed release fields only</p>
        </div>

        <dl class="grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-4">
            <x-flight-release.metric label="MO DY YR" :value="$model->maintenanceDate()" empty-text="Not confirmed" />
            <x-flight-release.metric label="Aircraft type" :value="$model->aircraftType()" empty-text="Not confirmed" />
            <x-flight-release.metric label="Aircraft number" :value="$model->tailNumber()" empty-text="Not confirmed" />
            <x-flight-release.metric label="Trip number" :value="$model->tripNumber()" empty-text="Not confirmed" />
            <x-flight-release.metric label="Departure" :value="$model->departure()" empty-text="Not confirmed" />
            <x-flight-release.metric label="Destination" :value="$model->destination()" empty-text="Not confirmed" />
            <x-flight-release.metric label="ETOPS flight" :value="$model->maintenanceEtopsLabel()" />
            <x-flight-release.metric :label="$model->maintenanceRampFuelLabel()" :value="$model->maintenanceRampFuel()" empty-text="Not confirmed" />
        </dl>
    </section>

    <section aria-labelledby="maintenance-crew-heading" class="flex min-w-0 flex-col gap-3">
        <div>
            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#4A5568] dark:text-slate-400">Confirmed crew section</p>
            <h3 id="maintenance-crew-heading" class="text-base font-bold text-[#1B365D] dark:text-slate-100">Crew list</h3>
        </div>

        @if ($model->crewMembers() === [])
            <p class="rounded-lg border border-dashed border-[#1B365D]/15 bg-[#F8F9FA] p-4 text-sm font-medium text-[#4A5568] dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-400">No confirmed crew list was found in the supported release section.</p>
        @else
            <ul class="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($model->crewMembers() as $member)
                    <li class="flex min-w-0 items-center gap-3 rounded-lg border border-[#1B365D]/10 bg-white p-3 dark:border-slate-700 dark:bg-slate-900">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#1B365D] text-[#C5A059] dark:bg-slate-950">
                            <x-heroicon-o-user class="h-4 w-4" />
                        </span>
                        <span class="min-w-0">
                            <span class="block break-words text-sm font-bold text-[#0B0E14] dark:text-slate-100">{{ $member['name'] }}</span>
                            @if ($member['details'])
                                <span class="block font-mono text-xs text-[#4A5568] dark:text-slate-400">{{ $member['details'] }}</span>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    @if ($model->hasMaintenanceSection())
        <section aria-labelledby="maintenance-summary-heading" class="overflow-hidden rounded-xl border border-[#1B365D]/10 bg-white dark:border-slate-700 dark:bg-slate-900">
            <header class="border-b border-[#1B365D]/10 bg-[#F8F9FA] px-4 py-3 dark:border-slate-700 dark:bg-slate-800">
                <h3 id="maintenance-summary-heading" class="text-xs font-bold uppercase tracking-[0.16em] text-[#1B365D] dark:text-slate-200">MEL / CDL</h3>
            </header>
            <dl class="grid grid-cols-1 gap-px bg-[#1B365D]/10 dark:bg-slate-700 sm:grid-cols-3">
                <x-flight-release.metric label="Items" :value="$model->maintenanceItemCountLabel()" class="rounded-none border-0" />
                <x-flight-release.metric label="Types" :value="$model->maintenanceTypeSummary()" empty-text="None listed" class="rounded-none border-0" />
                <x-flight-release.metric label="Source statuses" :value="$model->maintenanceStatusSummary()" empty-text="None listed" class="rounded-none border-0" />
            </dl>
        </section>
    @endif

    <x-flight-release.maintenance-caution />

    <section aria-labelledby="maintenance-items-heading" class="flex min-w-0 flex-col gap-3">
        <div>
            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#4A5568] dark:text-slate-400">Maintenance log</p>
            <h3 id="maintenance-items-heading" class="text-base font-bold text-[#1B365D] dark:text-slate-100">Source-listed items</h3>
        </div>

        @if (! $model->hasMaintenanceSection())
            <x-flight-release.empty-state
                title="No maintenance section found"
                message="Flight and crew context remains available from confirmed shared release fields. No MEL, CDL, or DMI items were found."
                icon="document-magnifying-glass"
                class="min-h-44 rounded-lg bg-[#F8F9FA] dark:bg-slate-800/60"
            />
        @elseif ($model->maintenanceItems() === [])
            <x-flight-release.empty-state
                title="No maintenance items listed"
                message="The confirmed maintenance section is present and contains no supported MEL, CDL, NEF, or DMI items."
                icon="clipboard-document-check"
                class="min-h-44 rounded-lg bg-[#F8F9FA] dark:bg-slate-800/60"
            />
        @else
            <x-flight-release.maintenance-items
                :items="$model->maintenanceItems()"
                id-prefix="maintenance-item-number"
            />
        @endif
    </section>

    <x-flight-release.source-evidence message="Maintenance source fragments and control evidence remain private to this extraction result and are not included in the Livewire snapshot." />
</div>
