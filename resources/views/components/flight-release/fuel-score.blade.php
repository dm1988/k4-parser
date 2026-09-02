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

    @if ($model->fuelScoreWaypoints() !== [])
        <section
            aria-labelledby="fuel-waypoint-heading"
            class="flex min-w-0 flex-col gap-3"
            x-data="waypointFuelMonitor(@js($model->fuelScoreWaypoints()))"
        >
            <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#4A5568] dark:text-slate-400">Source-backed monitoring</p>
                    <h3 id="fuel-waypoint-heading" class="text-base font-bold text-[#1B365D] dark:text-slate-100">Waypoint fuel</h3>
                </div>

                <label class="flex w-full max-w-48 flex-col gap-1 text-xs font-semibold text-[#1B365D] dark:text-slate-200">
                    Off time (UTC)
                    <input
                        aria-describedby="off-time-help"
                        autocomplete="off"
                        class="rounded-md border-[#1B365D]/20 bg-white font-mono text-sm text-[#0B0E14] focus:border-[#1B365D] focus:ring-[#1B365D] dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
                        inputmode="numeric"
                        maxlength="4"
                        pattern="(?:[01][0-9]|2[0-3])[0-5][0-9]"
                        placeholder="HHMM"
                        type="text"
                        x-model="offTime"
                    >
                    <span id="off-time-help" class="font-normal text-[#4A5568] dark:text-slate-400" x-text="offTimeMessage"></span>
                </label>
            </div>

            <details class="group rounded-lg border border-[#1B365D]/10 bg-white dark:border-slate-700 dark:bg-slate-900" open>
                <summary class="cursor-pointer px-3 py-2 text-xs font-bold text-[#1B365D] marker:text-[#1B365D] dark:text-slate-100 dark:marker:text-slate-300">More…</summary>
                <div class="overflow-x-auto border-t border-[#1B365D]/10 dark:border-slate-700">
                    <table class="min-w-full divide-y divide-[#1B365D]/10 text-left text-xs dark:divide-slate-700">
                        <thead class="bg-[#F4F7FA] text-[9px] uppercase tracking-[0.14em] text-[#4A5568] dark:bg-slate-800 dark:text-slate-400">
                            <tr>
                                <th class="px-3 py-2 font-semibold" scope="col">Waypoint</th>
                                <th class="px-3 py-2 font-semibold" scope="col" x-cloak x-show="hasCalculatedEtas">Planned ETA</th>
                                <th class="px-3 py-2 font-semibold" scope="col">Leg</th>
                                <th class="px-3 py-2 font-semibold" scope="col">Cumulative</th>
                                <th class="px-3 py-2 font-semibold" scope="col">Remaining fuel</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#1B365D]/10 font-mono text-[#0B0E14] dark:divide-slate-800 dark:text-slate-100">
                            <template x-for="(waypoint, index) in waypoints" :key="index">
                                <tr>
                                    <td class="whitespace-nowrap px-3 py-2 font-bold" x-text="waypoint.identifier"></td>
                                    <td class="whitespace-nowrap px-3 py-2" x-cloak x-show="hasCalculatedEtas" x-text="plannedEta(waypoint)"></td>
                                    <td class="whitespace-nowrap px-3 py-2" x-text="durationLabel(waypoint.legDurationMinutes)"></td>
                                    <td class="whitespace-nowrap px-3 py-2" x-text="durationLabel(waypoint.cumulativeDurationMinutes)"></td>
                                    <td class="whitespace-nowrap px-3 py-2" x-text="waypoint.remainingFuel ?? 'Not present'"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </details>

            <p class="text-xs leading-5 text-[#4A5568] dark:text-slate-400">Planned ETA is calculated in this browser from the entered Off time and confirmed cumulative duration. It does not alter extracted release data.</p>
        </section>
    @endif

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
