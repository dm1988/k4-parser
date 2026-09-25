<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Offline fuel calculator · {{ config('app.name') }}</title>
        <x-theme-initializer />
        @vite(['resources/css/app.css', 'resources/js/offline-fuel-score.js'])
    </head>
    <body class="min-h-screen bg-[#F8F9FA] font-sans text-[#0B0E14] dark:bg-gray-950 dark:text-slate-100">
        <main class="mx-auto flex max-w-6xl flex-col gap-6 px-4 py-6 sm:px-6 sm:py-10" x-data="offlineFuelScore(@js($calculator))">
            <header class="rounded-2xl bg-[#1B365D] px-5 py-6 text-white sm:px-8">
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-[#C5A059]">Flight Plan Brief</p>
                <h1 class="mt-2 text-2xl font-bold sm:text-3xl">Offline fuel calculator</h1>
                <p class="mt-2 font-mono text-sm text-slate-200">{{ $flightNumber ?? 'Flight number not present' }} · {{ $flightDate ?? 'Flight date not present' }}</p>
                <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-200">Enter Off time for waypoint ETAs. Add actual fuel readings to compare fuel and burn against the release and estimate fuel at destination. Calculations run in this tab after the page loads; reopening or refreshing requires a connection.</p>
            </header>

            <section aria-labelledby="calculator-inputs-heading" class="rounded-xl border border-[#1B365D]/10 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-6">
                <h2 id="calculator-inputs-heading" class="text-lg font-bold text-[#1B365D] dark:text-slate-100">Inputs</h2>
                <p class="mt-1 text-sm text-[#4A5568] dark:text-slate-400">Confirmed release takeoff fuel: <span class="font-mono font-semibold" x-text="sourceFuelLabel(takeoffFuel)">Not present in this release</span></p>
                <p class="mt-1 text-sm text-[#4A5568] dark:text-slate-400">Confirmed estimated landing fuel: <span class="font-mono font-semibold" x-text="sourceFuelLabel(estimatedLandingFuel)">Not present in this release</span></p>
                <div class="mt-4 flex flex-wrap items-end gap-4">
                    <label class="flex min-w-40 flex-col gap-1 text-sm font-semibold text-[#1B365D] dark:text-slate-200">
                        Off time (UTC, HHMM)
                        <input type="text" inputmode="numeric" maxlength="4" autocomplete="off" placeholder="0000–2359" x-model="offTime"
                            class="rounded-lg border-[#1B365D]/20 bg-white font-mono text-[#0B0E14] focus:border-[#1B365D] focus:ring-[#C5A059] dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">
                    </label>
                    <label class="flex min-w-44 flex-col gap-1 text-sm font-semibold text-[#1B365D] dark:text-slate-200">
                        <span class="whitespace-nowrap">Starting FOB at takeoff (<span x-text="fuelUnit?.toUpperCase() ?? 'unit unavailable'"></span>)</span>
                        <input type="text" inputmode="decimal" autocomplete="off" placeholder="Fuel amount" x-model="startingFob" :disabled="fuelUnit === null"
                            class="rounded-lg border-[#1B365D]/20 bg-white font-mono text-[#0B0E14] focus:border-[#1B365D] focus:ring-[#C5A059] disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">
                    </label>
                    <button type="button" x-on:click="reset()" class="rounded-lg border border-[#1B365D]/20 px-4 py-2 text-sm font-semibold text-[#1B365D] transition hover:bg-[#F8F9FA] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#C5A059] dark:border-slate-600 dark:text-slate-100 dark:hover:bg-slate-800">Reset inputs</button>
                </div>
                <p class="mt-3 text-xs text-[#4A5568] dark:text-slate-400">Starting FOB is the actual fuel at takeoff and is used for cumulative burn at every waypoint. ETA needs only Off time and a confirmed cumulative duration. TBO is the release's cumulative planned burn. Source quantities must use the same unit. Displayed fuel values are rounded to two decimal places; calculations use full precision.</p>
            </section>

            <section aria-labelledby="calculator-waypoints-heading" class="min-w-0 rounded-xl border border-[#1B365D]/10 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="px-4 py-4 sm:px-6">
                    <h2 id="calculator-waypoints-heading" class="text-lg font-bold text-[#1B365D] dark:text-slate-100">Waypoint estimates</h2>
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
                                                :aria-label="`${waypoint.expanded ? 'Collapse' : 'Expand'} details for ${waypoint.identifier}`"
                                                class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-[#4A5568] transition hover:bg-[#1B365D]/5 hover:text-[#1B365D] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#C5A059] dark:text-slate-400 dark:hover:bg-slate-800/60 dark:hover:text-slate-200">
                                                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 transition-transform" :class="waypoint.expanded ? 'rotate-90' : ''">
                                                    <path d="m9 5 7 7-7 7" />
                                                </svg>
                                            </button>
                                            <span x-text="waypoint.identifier"></span>
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
                                        <span class="inline-flex items-baseline justify-end gap-1" :class="hasActualFob(waypoint) ? '' : 'text-slate-400 dark:text-slate-600'">
                                            <span x-text="destinationValue(waypoint)"></span>
                                            <span x-show="destinationUnit(waypoint)" class="font-sans text-xs text-[#4A5568] dark:text-slate-400" x-text="destinationUnit(waypoint)"></span>
                                        </span>
                                    </td>
                                </tr>
                                <tr x-show="waypoint.expanded" x-cloak :id="`waypoint-details-${index}`" class="bg-[#F4F7FA] dark:bg-slate-800/50">
                                    <td colspan="5" class="px-3 pb-4 pt-2">
                                        <div class="grid gap-4 font-sans text-xs md:grid-cols-2">
                                            <div class="flex flex-col gap-2">
                                                <span class="font-semibold text-[#1B365D] dark:text-slate-200">Time (UTC)</span>
                                                <span class="text-[#4A5568] dark:text-slate-400" x-show="etaReason(waypoint)" x-text="etaReason(waypoint)"></span>
                                                <label class="flex flex-col gap-1 font-semibold text-[#1B365D] dark:text-slate-200">
                                                    <span>ATA (UTC, HHMM) at <span x-text="waypoint.identifier"></span></span>
                                                    <input type="text" inputmode="numeric" maxlength="4" autocomplete="off" placeholder="HHMM" x-model="waypoint.ata"
                                                        class="w-28 rounded-md border-[#1B365D]/20 bg-white font-mono text-sm text-[#0B0E14] focus:border-[#1B365D] focus:ring-[#C5A059] dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">
                                                </label>
                                                <span class="text-red-700 dark:text-red-400" x-show="ataReason(waypoint)" x-text="ataReason(waypoint)"></span>
                                                <div x-show="hasValidAta(waypoint)" class="flex flex-col gap-1">
                                                    <span :class="varianceClasses(etaAtaDifference(waypoint))">ETA vs ATA: <span class="font-mono" x-text="etaAtaLabel(waypoint)"></span></span>
                                                    <span class="text-[#4A5568] dark:text-slate-400" x-show="etaAtaDifference(waypoint) === null" x-text="etaAtaReason(waypoint)"></span>
                                                </div>
                                            </div>
                                            <div class="flex flex-col gap-2">
                                                <span class="font-semibold text-[#1B365D] dark:text-slate-200">Fuel and burn</span>
                                                <span class="text-[#4A5568] dark:text-slate-400">Planned FOB (FRMG): <span class="font-mono" x-text="sourceFuelLabel(waypoint.remainingFuel)"></span></span>
                                                <span class="text-[#4A5568] dark:text-slate-400">TBO (source, cumulative): <span class="font-mono" x-text="plannedBurnLabel(waypoint)"></span></span>
                                                <label class="flex flex-col gap-1 font-semibold text-[#1B365D] dark:text-slate-200">
                                                    <span>AFOB (<span x-text="fuelUnit?.toUpperCase() ?? 'unit unavailable'"></span>) at <span x-text="waypoint.identifier"></span></span>
                                                    <input type="text" inputmode="decimal" autocomplete="off" placeholder="Actual fuel" x-model="waypoint.actualFob" :disabled="fuelUnit === null"
                                                        class="w-36 rounded-md border-[#1B365D]/20 bg-white font-mono text-sm text-[#0B0E14] focus:border-[#1B365D] focus:ring-[#C5A059] disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">
                                                </label>
                                                <div x-show="hasActualFob(waypoint)" class="flex flex-col gap-1">
                                                    <span :class="varianceClasses(fobVariance(waypoint))">FOB vs plan: <span class="font-mono" x-text="varianceLabel(fobVariance(waypoint))"></span></span>
                                                    <span class="text-[#4A5568] dark:text-slate-400" x-show="fobVariance(waypoint) === null" x-text="fobReason(waypoint)"></span>
                                                    <span class="text-[#4A5568] dark:text-slate-400">Actual burn (ABO, cumulative): <span class="font-mono" x-text="fuelLabel(actualBurn(waypoint))"></span></span>
                                                    <span :class="varianceClasses(burnVariance(waypoint))">Cumulative burn vs TBO: <span class="font-mono" x-text="varianceLabel(burnVariance(waypoint))"></span></span>
                                                    <span class="text-[#4A5568] dark:text-slate-400" x-show="burnVariance(waypoint) === null" x-text="burnReason(waypoint)"></span>
                                                    <span class="text-[#4A5568] dark:text-slate-400" x-show="estimatedDestinationFuel(waypoint) === null" x-text="destinationReason(waypoint)"></span>
                                                </div>
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
