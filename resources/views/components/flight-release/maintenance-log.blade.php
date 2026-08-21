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
            <x-flight-release.metric label="Date" :value="$model->flightDate()" empty-text="Not confirmed" />
            <x-flight-release.metric label="Aircraft type" :value="$model->aircraftType()" empty-text="Not confirmed" />
            <x-flight-release.metric label="Tail number" :value="$model->tailNumber()" empty-text="Not confirmed" />
            <x-flight-release.metric label="Trip number" :value="$model->tripNumber()" empty-text="Not confirmed" />
            <x-flight-release.metric label="Departure" :value="$model->departure()" empty-text="Not confirmed" />
            <x-flight-release.metric label="Destination" :value="$model->destination()" empty-text="Not confirmed" />
            <x-flight-release.metric label="ETOPS flight" :value="$model->maintenanceEtopsLabel()" />
            <x-flight-release.metric label="Estimated ramp fuel" :value="$model->maintenanceRampFuel()" empty-text="Not confirmed" />
        </dl>
    </section>

    @if ($model->hasMaintenanceSection())
        <section aria-labelledby="maintenance-summary-heading" class="overflow-hidden rounded-xl border border-[#1B365D]/10 bg-white dark:border-slate-700 dark:bg-slate-900">
            <header class="border-b border-[#1B365D]/10 bg-[#F8F9FA] px-4 py-3 dark:border-slate-700 dark:bg-slate-800">
                <h3 id="maintenance-summary-heading" class="text-xs font-bold uppercase tracking-[0.16em] text-[#1B365D] dark:text-slate-200">Source summary</h3>
            </header>
            <dl class="grid grid-cols-1 gap-px bg-[#1B365D]/10 dark:bg-slate-700 sm:grid-cols-3">
                <x-flight-release.metric label="Items" :value="$model->maintenanceItemCountLabel()" class="rounded-none border-0" />
                <x-flight-release.metric label="Types" :value="$model->maintenanceTypeSummary()" empty-text="None listed" class="rounded-none border-0" />
                <x-flight-release.metric label="Source statuses" :value="$model->maintenanceStatusSummary()" empty-text="None listed" class="rounded-none border-0" />
            </dl>
        </section>
    @endif

    <aside class="rounded-lg border border-amber-300/70 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-400/25 dark:bg-amber-400/10 dark:text-amber-100">
        <div class="flex items-start gap-3">
            <x-heroicon-o-exclamation-triangle class="h-5 w-5 shrink-0" />
            <div class="flex min-w-0 flex-col gap-1">
                <p class="font-bold">No airworthiness determination</p>
                <p class="leading-5">This view repeats source-listed maintenance information and does not determine dispatchability. Review the controlling maintenance record and procedures.</p>
            </div>
        </div>
    </aside>

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
                message="The confirmed maintenance section is present and contains no supported MEL, CDL, or DMI items."
                icon="clipboard-document-check"
                class="min-h-44 rounded-lg bg-[#F8F9FA] dark:bg-slate-800/60"
            />
        @else
            <ol class="flex min-w-0 flex-col gap-3">
                @foreach ($model->maintenanceItems() as $item)
                    <li>
                        <article class="overflow-hidden rounded-xl border border-[#1B365D]/10 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                            <header class="flex flex-wrap items-start justify-between gap-3 border-b border-[#1B365D]/10 bg-[#F8F9FA] px-4 py-3 dark:border-slate-700 dark:bg-slate-800">
                                <div class="flex min-w-0 items-center gap-2">
                                    <span class="rounded-full bg-[#1B365D] px-2.5 py-1 text-[10px] font-bold tracking-[0.12em] text-white dark:bg-slate-950">{{ $item['type'] }}</span>
                                    <h4 class="break-all font-mono text-sm font-bold text-[#1B365D] dark:text-slate-100">{{ $item['number'] }}</h4>
                                </div>
                                @if ($item['status'])
                                    <span class="rounded-full bg-slate-200 px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.12em] text-slate-700 dark:bg-slate-700 dark:text-slate-200">{{ $item['status'] }}</span>
                                @endif
                            </header>

                            <div class="flex min-w-0 flex-col gap-4 p-4">
                                <div class="flex min-w-0 flex-col gap-1">
                                    <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-[#4A5568] dark:text-slate-400">Description</p>
                                    <p class="break-words text-sm leading-6 text-[#0B0E14] dark:text-slate-100">{{ $item['description'] }}</p>
                                </div>

                                @if ($item['reference'])
                                    <div class="flex min-w-0 flex-col gap-1">
                                        <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-[#4A5568] dark:text-slate-400">DMI / reference</p>
                                        <p class="break-all font-mono text-sm font-bold text-[#1B365D] dark:text-sky-300">{{ $item['reference'] }}</p>
                                    </div>
                                @endif

                                @if ($item['limitations'] || $item['procedures'])
                                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                        @if ($item['limitations'])
                                            <div class="flex min-w-0 flex-col gap-1 rounded-lg border border-amber-300/60 bg-amber-50 p-3 dark:border-amber-400/20 dark:bg-amber-400/10">
                                                <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-amber-800 dark:text-amber-200">Source limitation</p>
                                                <p class="break-words text-sm leading-5 text-amber-950 dark:text-amber-100">{{ $item['limitations'] }}</p>
                                            </div>
                                        @endif
                                        @if ($item['procedures'])
                                            <div class="flex min-w-0 flex-col gap-1 rounded-lg border border-[#1B365D]/10 bg-[#F8F9FA] p-3 dark:border-slate-700 dark:bg-slate-800">
                                                <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-[#4A5568] dark:text-slate-400">Source procedure</p>
                                                <p class="break-words text-sm leading-5 text-[#0B0E14] dark:text-slate-100">{{ $item['procedures'] }}</p>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </article>
                    </li>
                @endforeach
            </ol>
        @endif
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

    <x-flight-release.source-evidence message="Maintenance source fragments and control evidence remain private to this extraction result and are not included in the Livewire snapshot." />
</div>
