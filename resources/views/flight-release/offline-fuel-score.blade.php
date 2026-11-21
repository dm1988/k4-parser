<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Offline fuel calculator · {{ config('app.name') }}</title>
        <x-theme-initializer />
        {{ $calculatorAssets }}
    </head>
    <body class="min-h-screen bg-[#F8F9FA] font-sans text-[#0B0E14] dark:bg-gray-950 dark:text-slate-100">
        <main class="mx-auto flex max-w-6xl flex-col gap-6 px-4 py-6 sm:px-6 sm:py-10" x-data="offlineFuelScore(@js($calculator), @js($draftScope), @js($offlineRecovery))">
            <header class="rounded-2xl bg-[#1B365D] px-5 py-6 text-white sm:px-8">
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-[#C5A059]">Flight Plan Brief</p>
                <h1 class="mt-2 text-2xl font-bold sm:text-3xl">Offline fuel calculator</h1>
                <p class="mt-2 font-mono text-sm text-slate-200">{{ $flightNumber ?? 'Flight number not present' }} · {{ $flightDate ?? 'Flight date not present' }}</p>
                <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-200">Enter Off time for waypoint ETAs. Add actual fuel readings to compare fuel and burn against the release and estimate fuel at destination. Inputs are saved on this device for this flight and restored after switching apps, refreshing, or reopening the calculator. Use Reset inputs to clear them. Once offline recovery is ready, this tab can reload without a connection, including after the browser unloads it.</p>
                <p role="status" class="mt-2 text-sm font-semibold text-[#C5A059]" x-text="offlineMessage">Preparing offline recovery…</p>
            </header>

            <x-flight-release.release-status :status="$releaseStatus" />

            <section aria-labelledby="calculator-inputs-heading" class="rounded-xl border border-[#1B365D]/10 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-6">
                <h2 id="calculator-inputs-heading" class="text-lg font-bold text-[#1B365D] dark:text-slate-100">Inputs <x-flight-release.planning-qualifier :show="$releaseStatus['preliminary']" label="Preliminary plan comparisons" /></h2>
                <div class="mt-2 flex flex-wrap gap-x-6 gap-y-1 text-xs text-[#4A5568] dark:text-slate-400">
                    <p>Release takeoff: <span class="font-mono font-semibold" x-text="sourceFuelLabel(takeoffFuel)">Not present in this release</span></p>
                    <p>Release est. landing: <span class="font-mono font-semibold" x-text="sourceFuelLabel(estimatedLandingFuel)">Not present in this release</span></p>
                </div>
                <div class="mt-4 grid items-end gap-4 sm:grid-cols-[12rem_minmax(0,1fr)] lg:grid-cols-[12rem_24rem_auto]">
                    <label for="fuel-score-off-time" class="flex min-w-0 flex-col gap-1.5 text-sm font-semibold text-[#1B365D] dark:text-slate-200">
                        Off time (UTC, HHMM)
                        <input id="fuel-score-off-time" name="off_time" type="text" inputmode="numeric" maxlength="4" autocomplete="off" placeholder="0000–2359" x-model="offTime" x-on:input="saveDraft()"
                            class="h-11 w-full rounded-lg border-[#1B365D]/20 bg-white px-3 py-2 font-mono text-sm text-[#0B0E14] focus:border-[#1B365D] focus:ring-[#C5A059] dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">
                    </label>
                    <label for="fuel-score-starting-fob" class="relative flex min-w-0 flex-col gap-1.5 text-sm font-semibold text-[#1B365D] dark:text-slate-200">
                        <span>Starting FOB at takeoff (<span x-text="fuelUnit?.toUpperCase() ?? 'unit unavailable'"></span>)</span>
                        <input id="fuel-score-starting-fob" name="starting_fob" type="text" inputmode="decimal" autocomplete="off" placeholder="Fuel amount" x-model="startingFob" x-on:input="saveDraft()" :disabled="fuelUnit === null" aria-describedby="fuel-score-input-help fuel-score-starting-fob-value"
                            class="h-11 w-full rounded-lg border-[#1B365D]/20 bg-white py-2 pl-3 pr-32 font-mono text-sm text-[#0B0E14] focus:border-[#1B365D] focus:ring-[#C5A059] disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">
                        <span id="fuel-score-starting-fob-value" class="pointer-events-none absolute bottom-0 right-3 flex h-11 items-center gap-1 whitespace-nowrap font-mono text-xs font-normal text-[#4A5568] dark:text-slate-400" x-show="fuelInputLabel(startingFob)" x-cloak>
                            <span aria-hidden="true">=</span><span x-text="fuelInputLabel(startingFob)"></span>
                        </span>
                    </label>
                    <button type="button" x-on:click="reset()" class="h-11 justify-self-start rounded-lg border border-[#1B365D]/20 px-4 py-2 text-sm font-semibold text-[#1B365D] transition hover:bg-[#F8F9FA] focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-[#C5A059] dark:border-slate-600 dark:text-slate-100 dark:hover:bg-slate-800">Reset inputs</button>
                </div>
                <p id="fuel-score-input-help" class="mt-3 text-xs text-[#4A5568] dark:text-slate-400" x-show="fuelUnit === 'lb'" x-cloak>LB: 85 or 85000 = 85,000 LB; 85.5 = 85,500 LB.</p>
                <p x-show="draftUnavailable" x-cloak role="status" class="mt-3 text-sm text-amber-800 dark:text-amber-300">Browser storage is unavailable. Saved inputs may not match this page after a reload.</p>
                <details class="mt-3 text-xs text-[#4A5568] dark:text-slate-400">
                    <summary class="w-fit rounded font-semibold focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-[#C5A059]">Calculation details</summary>
                    <div class="mt-2 flex max-w-3xl flex-col gap-2 leading-5">
                        <p>Starting FOB is the actual fuel at takeoff. TBO is planned cumulative burn. ETA uses Off time and cumulative duration. Source fuel units must match. Display rounds to two decimals; calculations retain full precision.</p>
                        <p x-show="fuelUnit === 'lb'" x-cloak>For LB, 1–3 digits before the decimal mean thousands; 4+ digits mean exact pounds.</p>
                    </div>
                </details>
            </section>

            <section aria-labelledby="calculator-waypoints-heading" class="min-w-0 rounded-xl border border-[#1B365D]/10 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="px-4 py-4 sm:px-6">
                    <h2 id="calculator-waypoints-heading" class="text-lg font-bold text-[#1B365D] dark:text-slate-100">Waypoint estimates <x-flight-release.planning-qualifier :show="$releaseStatus['preliminary']" label="Preliminary plan comparisons" /></h2>
                    <p class="text-sm text-[#4A5568] dark:text-slate-400">Times are UTC; T/TME is cumulative time. Collapsed rows show planned FOB from the release. Expand a waypoint for ATA, AFOB, and burn comparisons. Positive fuel difference means above plan; positive cumulative burn difference means below TBO.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-[#F4F7FA] text-xs uppercase tracking-wide text-[#4A5568] dark:bg-slate-800 dark:text-slate-300">
                            <tr>
                                <th scope="col" class="px-3 py-2">Waypoint</th>
                                <th scope="col" class="px-3 py-2 text-right">ETA</th>
                                <th scope="col" class="px-3 py-2 text-right">T/TME</th>
                                <th scope="col" class="px-3 py-2 text-right">Fuel on board</th>
                                <th scope="col" class="px-3 py-2 text-right">Estimated fuel at destination</th>
                            </tr>
                        </thead>
                        <template x-for="(waypoint, index) in waypoints" :key="index">
                            <tbody class="border-t border-[#1B365D]/5 font-mono dark:border-slate-800/60">
                                <tr class="transition-colors hover:bg-[#F4F7FA] dark:hover:bg-slate-800/40">
                                    <th scope="row" class="whitespace-nowrap px-3 py-1 text-left align-middle font-normal">
                                        <div class="flex items-center gap-2">
                                            <button type="button" x-on:click="toggleWaypoint(waypoint)" :aria-expanded="waypoint.expanded.toString()" :aria-controls="`waypoint-details-${index}`"
                                                :aria-label="`${waypoint.expanded ? 'Collapse' : 'Expand'} details for ${waypoint.displayLabel}`"
                                                class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-[#4A5568] transition hover:bg-[#1B365D]/5 hover:text-[#1B365D] focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-[#C5A059] dark:text-slate-400 dark:hover:bg-slate-800/60 dark:hover:text-slate-200">
                                                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 transition-transform" :class="waypoint.expanded ? 'rotate-90' : ''">
                                                    <path d="m9 5 7 7-7 7" />
                                                </svg>
                                            </button>
                                            <span x-text="waypoint.displayLabel"></span>
                                        </div>
                                    </th>
                                    <td class="whitespace-nowrap px-3 py-1 text-right align-middle" x-text="plannedEta(waypoint)"></td>
                                    <td class="whitespace-nowrap px-3 py-1 text-right align-middle">
                                        <span class="inline-flex items-baseline justify-end gap-1">
                                            <span x-text="durationValue(waypoint.cumulativeDurationMinutes)"></span>
                                            <span x-show="durationUnit(waypoint.cumulativeDurationMinutes)" class="font-sans text-xs text-[#4A5568] dark:text-slate-400" x-text="durationUnit(waypoint.cumulativeDurationMinutes)"></span>
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-1 text-right align-middle">
                                        <span class="inline-flex items-baseline justify-end gap-1">
                                            <span x-text="sourceFuelValue(waypoint.remainingFuel)"></span>
                                            <span x-show="sourceFuelUnit(waypoint.remainingFuel)" class="font-sans text-xs text-[#4A5568] dark:text-slate-400" x-text="sourceFuelUnit(waypoint.remainingFuel)"></span>
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-1 text-right align-middle">
                                        <span x-show="!waypoint.expanded" class="inline-flex items-baseline justify-end gap-1" :class="hasActualFob(waypoint) ? '' : 'text-slate-400 dark:text-slate-600'">
                                            <span x-text="destinationValue(waypoint)"></span>
                                            <span x-show="destinationUnit(waypoint)" class="font-sans text-xs text-[#4A5568] dark:text-slate-400" x-text="destinationUnit(waypoint)"></span>
                                        </span>
                                    </td>
                                </tr>
                                <tr x-show="waypoint.expanded" x-cloak :id="`waypoint-details-${index}`" class="bg-[#F4F7FA] dark:bg-slate-800/50">
                                    <td colspan="5" class="px-4 py-5 sm:px-6">
                                        <div class="grid items-start gap-6 font-sans text-xs leading-5 md:grid-cols-3 md:gap-8">
                                            <div class="flex min-w-0 flex-col gap-3">
                                                <span class="font-semibold text-[#1B365D] dark:text-slate-200">Time (UTC)</span>
                                                <label :for="`fuel-score-waypoint-${index}-ata`" class="flex flex-col gap-1 font-semibold text-[#1B365D] dark:text-slate-200">
                                                    <span>ATA (UTC, HHMM) at <span x-text="waypoint.displayLabel"></span></span>
                                                    <input :id="`fuel-score-waypoint-${index}-ata`" :name="`waypoints[${index}][ata]`" type="text" inputmode="numeric" maxlength="4" autocomplete="off" placeholder="HHMM" x-model="waypoint.ata" x-on:input="saveDraft()"
                                                        class="h-11 w-full rounded-md border-[#1B365D]/20 bg-white px-3 py-2 font-mono text-sm text-[#0B0E14] focus:border-[#1B365D] focus:ring-[#C5A059] dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">
                                                </label>
                                                <span class="text-[#4A5568] dark:text-slate-400" x-show="etaReason(waypoint)" x-text="etaReason(waypoint)"></span>
                                                <span class="text-red-700 dark:text-red-400" x-show="ataReason(waypoint)" x-text="ataReason(waypoint)"></span>
                                                <div x-show="hasValidAta(waypoint)" class="flex flex-col gap-1">
                                                    <span :class="varianceClasses(etaAtaDifference(waypoint))">ETA vs ATA: <span class="font-mono" x-text="etaAtaLabel(waypoint)"></span></span>
                                                    <span class="text-[#4A5568] dark:text-slate-400" x-show="etaAtaDifference(waypoint) === null" x-text="etaAtaReason(waypoint)"></span>
                                                </div>
                                            </div>
                                            <div class="flex min-w-0 flex-col gap-3">
                                                <span class="font-semibold text-[#1B365D] dark:text-slate-200">Fuel and burn</span>
                                                <label :for="`fuel-score-waypoint-${index}-actual-fob`" class="relative flex min-w-0 flex-col gap-1 font-semibold text-[#1B365D] dark:text-slate-200">
                                                    <span>AFOB (<span x-text="fuelUnit?.toUpperCase() ?? 'unit unavailable'"></span>) at <span x-text="waypoint.displayLabel"></span></span>
                                                    <input :id="`fuel-score-waypoint-${index}-actual-fob`" :name="`waypoints[${index}][actual_fob]`" type="text" inputmode="decimal" autocomplete="off" placeholder="Actual fuel" x-model="waypoint.actualFob" x-on:input="saveDraft()" :disabled="fuelUnit === null" :aria-describedby="`fuel-score-input-help fuel-score-waypoint-${index}-actual-fob-value`"
                                                        class="h-11 w-full rounded-md border-[#1B365D]/20 bg-white py-2 pl-3 pr-28 font-mono text-sm text-[#0B0E14] focus:border-[#1B365D] focus:ring-[#C5A059] disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">
                                                    <span :id="`fuel-score-waypoint-${index}-actual-fob-value`" class="pointer-events-none absolute bottom-0 right-3 flex h-11 items-center gap-1 whitespace-nowrap font-mono text-[11px] font-normal text-[#4A5568] dark:text-slate-400" x-show="fuelInputLabel(waypoint.actualFob)" x-cloak>
                                                        <span aria-hidden="true">=</span><span x-text="fuelInputLabel(waypoint.actualFob)"></span>
                                                    </span>
                                                </label>
                                                <span class="text-[#4A5568] dark:text-slate-400">TBO (source, cumulative): <span class="font-mono" x-text="plannedBurnLabel(waypoint)"></span></span>
                                                <div x-show="hasActualFob(waypoint)" class="flex flex-col gap-1">
                                                    <span :class="varianceClasses(fobVariance(waypoint))">FOB vs plan: <span class="font-mono" x-text="varianceLabel(fobVariance(waypoint))"></span></span>
                                                    <span class="text-[#4A5568] dark:text-slate-400" x-show="fobVariance(waypoint) === null" x-text="fobReason(waypoint)"></span>
                                                    <span class="text-[#4A5568] dark:text-slate-400">Actual burn (ABO, cumulative): <span class="font-mono" x-text="fuelLabel(actualBurn(waypoint))"></span></span>
                                                    <span :class="varianceClasses(burnVariance(waypoint))">Cumulative burn vs TBO: <span class="font-mono" x-text="varianceLabel(burnVariance(waypoint))"></span></span>
                                                    <span class="text-[#4A5568] dark:text-slate-400" x-show="burnVariance(waypoint) === null" x-text="burnReason(waypoint)"></span>
                                                </div>
                                            </div>
                                            <div class="flex min-w-0 flex-col gap-3 border-t border-[#1B365D]/10 pt-4 dark:border-slate-700 md:items-end md:border-l md:border-t-0 md:pl-6 md:pt-0 md:text-right">
                                                <span class="font-semibold text-[#1B365D] dark:text-slate-200">Estimated fuel at destination</span>
                                                <div class="flex flex-wrap items-baseline gap-2 text-[#1B365D] dark:text-slate-100 md:justify-end" :class="hasActualFob(waypoint) ? '' : 'opacity-50'" aria-live="polite" aria-atomic="true">
                                                    <span class="font-mono font-bold tabular-nums" :class="destinationUnit(waypoint) ? 'text-3xl leading-tight' : 'text-sm leading-6'" x-text="destinationValue(waypoint)"></span>
                                                    <span class="text-sm text-[#4A5568] dark:text-slate-400" x-show="destinationUnit(waypoint)" x-text="destinationUnit(waypoint)"></span>
                                                </div>
                                                <span class="text-[#4A5568] dark:text-slate-400" x-show="estimatedDestinationFuel(waypoint) === null" x-text="destinationReason(waypoint)"></span>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </template>
                    </table>
                </div>
                @if ($calculator['waypoints'] === [])
                    <p class="px-4 py-5 text-sm text-[#4A5568] dark:text-slate-400">No waypoints are present in this release.</p>
                @endif
            </section>

            <p class="text-sm leading-6 text-[#4A5568] dark:text-slate-400">Browser-calculated planning aids only. This page does not determine fuel compliance, dispatchability, or safety. Review the controlling flight release.</p>
            <noscript><p class="text-sm text-red-700">JavaScript is required to calculate waypoint estimates.</p></noscript>
        </main>
    </body>
</html>
