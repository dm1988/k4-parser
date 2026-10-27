@props(['trip'])
<div {{ $attributes->class('mt-3 min-w-0 space-y-3 text-sm') }} x-data="bidTripDetails" x-id="['trip-tab', 'trip-panel']">
    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800">
        <div class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1">
            <p class="min-w-0 flex-1 truncate rounded-full bg-white px-3 py-1 font-mono text-xs font-semibold dark:bg-slate-900" title="{{ $trip['route_label'] }}">{{ $trip['route_label'] }}</p>
            <span class="font-mono text-xs text-slate-500 dark:text-slate-400">{{ $trip['start_date'] }} → {{ $trip['end_date'] }} UTC</span>
        </div>
        <dl class="mt-2 flex flex-wrap gap-x-5 gap-y-2 text-xs">
            @if (filled($trip['report'] ?? null))<div class="flex gap-2"><dt class="text-slate-500 dark:text-slate-400">Report · UTC</dt><dd class="font-mono font-semibold">{{ $trip['report'] }}</dd></div>@endif
            @if (filled($trip['release'] ?? null))<div class="flex gap-2"><dt class="text-slate-500 dark:text-slate-400">Release · UTC</dt><dd class="font-mono font-semibold">{{ $trip['release'] }}</dd></div>@endif
            @if (isset($trip['duty_days']))<div class="flex gap-2"><dt class="text-slate-500 dark:text-slate-400">Duty days (source)</dt><dd class="font-mono">{{ $trip['duty_days'] }}</dd></div>@endif
            @if (filled($trip['crew_label'] ?? null))<div class="flex gap-2"><dt class="text-slate-500 dark:text-slate-400">Crew complement</dt><dd class="font-mono">{{ $trip['crew_label'] }}</dd></div>@endif
            @if (isset($trip['credit_minutes']))<div class="flex gap-2"><dt class="text-slate-500 dark:text-slate-400">Base credit · h:mm</dt><dd class="font-mono">{{ $trip['credit_time_label'] }}</dd></div>@endif
            @if (isset($trip['block_minutes']))<div class="flex gap-2"><dt class="text-slate-500 dark:text-slate-400">Block · h:mm</dt><dd class="font-mono">{{ $trip['block_time_label'] }}</dd></div>@endif
        </dl>
    </div>

    <div role="tablist" aria-label="Trip {{ $trip['id'] }} details" @keydown="handleTabKey($event)" class="flex flex-wrap gap-1 border-b border-slate-200 pb-2 dark:border-slate-700">
        @foreach (['timeline' => 'Timeline / Legs', 'layovers' => 'Layovers & Rest', 'metadata' => 'Pairing Metadata'] as $key => $label)
            <button type="button" role="tab" x-ref="{{ $key }}Tab" :id="$id('trip-tab', '{{ $key }}')" :aria-controls="$id('trip-panel', '{{ $key }}')" :aria-selected="tab === '{{ $key }}'" :tabindex="tab === '{{ $key }}' ? 0 : -1" @click="tab = '{{ $key }}'" :class="tab === '{{ $key }}' ? 'bg-[#1B365D] text-white dark:bg-blue-300 dark:text-slate-950' : 'text-slate-600 dark:text-slate-300'" class="min-h-11 rounded-md px-3 text-xs font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-500">{{ $label }}</button>
        @endforeach
    </div>

    <section role="tabpanel" :id="$id('trip-panel', 'timeline')" :aria-labelledby="$id('trip-tab', 'timeline')" tabindex="0" x-show="tab === 'timeline'" class="space-y-3 rounded-md focus-visible:outline-2 focus-visible:outline-amber-500">
        @if ($trip['legs'] !== [])<x-crew-bids.leg-timeline :trip="$trip" />@endif
    </section>

    <section role="tabpanel" :id="$id('trip-panel', 'layovers')" :aria-labelledby="$id('trip-tab', 'layovers')" tabindex="0" x-show="tab === 'layovers'" x-cloak class="space-y-3 rounded-md focus-visible:outline-2 focus-visible:outline-amber-500">
        <p class="text-xs text-slate-500 dark:text-slate-400">Calculated from scheduled UTC times, arrival to next departure. Source rest is shown separately. Amber: under 10h. Blue: 24h or more. Connections under 3h are grouped separately.</p>
        @if (isset($trip['minimum_rest']))<p class="text-xs">Minimum source rest · h:mm: <span class="font-mono font-semibold">{{ $trip['minimum_rest'] }}</span></p>@endif
        @foreach (['short' => 'Connections under 3h', 'longer' => 'Longer layovers'] as $group => $label)
            @if ($trip['layover_groups'][$group] !== [])
                <div>
                    <h5 class="mb-2 text-xs font-semibold">{{ $label }}</h5>
                    <table class="w-full table-fixed text-left text-xs">
                        <caption class="sr-only">{{ $label }} for trip {{ $trip['id'] }} · UTC</caption>
                        <thead class="text-slate-500 dark:text-slate-400"><tr><th scope="col" class="w-1/2 px-3 py-2">Airport / interval</th><th scope="col" class="px-3 py-2">Layover / source rest</th></tr></thead>
                        <tbody>
                            @foreach ($trip['layover_groups'][$group] as $layover)
                                <tr @class(['border-t border-slate-200 align-top dark:border-slate-700', 'bg-amber-50 dark:bg-amber-950/40' => $layover['highlight'] === 'short', 'bg-blue-50 dark:bg-blue-950/40' => $layover['highlight'] === 'extended'])>
                                    <th scope="row" class="px-3 py-2 font-normal"><span class="font-mono font-semibold">{{ $layover['airport'] }}</span><details class="mt-1"><summary class="min-h-8 cursor-pointer rounded underline focus-visible:outline-2 focus-visible:outline-amber-500">UTC interval</summary><p class="mt-1 break-words font-mono text-[10px]">{{ $layover['start'] }} → {{ $layover['end'] }}</p></details></th>
                                    <td class="px-3 py-2"><span class="font-mono font-semibold">{{ $layover['duration'] }}</span>
                                        @if ($layover['highlight'] === 'short')<span class="mt-1 inline-flex rounded-full border border-amber-300 bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-900 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-200">Short layover · under 10h</span>
                                        @elseif ($layover['highlight'] === 'extended')<span class="mt-1 inline-flex rounded-full border border-blue-300 bg-blue-100 px-2 py-0.5 text-[10px] font-semibold text-[#1B365D] dark:border-blue-700 dark:bg-blue-950 dark:text-blue-200">Extended layover · 24h+</span>@endif
                                        @if ($group === 'short')<span class="mt-1 block text-[10px] font-medium text-amber-800 dark:text-amber-300">Short connection · under 3h</span>@endif
                                        @if (isset($layover['source_rest']))<p class="mt-1 text-[10px] text-slate-500 dark:text-slate-400">Source rest (h:mm): <span class="font-mono">{{ $layover['source_rest'] }}</span></p>@endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endforeach
        @foreach ($trip['legs'] as $leg)
            @if ($leg['connection_status'] === 'overlap')<p class="text-xs text-amber-800 dark:text-amber-300">Layover unavailable: consecutive leg times overlap.</p>
            @elseif ($leg['connection_status'] === 'airport_change')<p class="text-xs text-amber-800 dark:text-amber-300">Layover unavailable: next leg departs from a different airport.</p>@endif
        @endforeach
    </section>

    <section role="tabpanel" :id="$id('trip-panel', 'metadata')" :aria-labelledby="$id('trip-tab', 'metadata')" tabindex="0" x-show="tab === 'metadata'" x-cloak class="rounded-md focus-visible:outline-2 focus-visible:outline-amber-500">
        <dl class="grid grid-cols-1 gap-3 text-xs sm:grid-cols-2">
            <div class="sm:col-span-2"><dt class="text-slate-500 dark:text-slate-400">Full route</dt><dd class="break-words font-mono">{{ $trip['route_label'] }}</dd></div>
            @if (filled($trip['report'] ?? null))<div><dt class="text-slate-500 dark:text-slate-400">Report · UTC</dt><dd class="font-mono">{{ $trip['start_date'] }} {{ $trip['report'] }}</dd></div>@endif
            @if (filled($trip['release'] ?? null))<div><dt class="text-slate-500 dark:text-slate-400">Release · UTC</dt><dd class="font-mono">{{ $trip['end_date'] }} {{ $trip['release'] }}</dd></div>@endif
            <div><dt class="text-slate-500 dark:text-slate-400">Start / end airport</dt><dd class="font-mono">{{ $trip['start_city'] }} → {{ $trip['end_city'] }}</dd></div>
            @if ($trip['layovers'] !== [])<div><dt class="text-slate-500 dark:text-slate-400">Layover airports</dt><dd class="font-mono">{{ $trip['layover_label'] }}</dd></div>@endif
            @if (isset($trip['duty_days']))<div><dt class="text-slate-500 dark:text-slate-400">Duty days (source)</dt><dd class="font-mono">{{ $trip['duty_days'] }}</dd></div>@endif
            @if (filled($trip['crew_label'] ?? null))<div><dt class="text-slate-500 dark:text-slate-400">Crew complement</dt><dd class="font-mono">{{ $trip['crew_label'] }}</dd></div>@endif
        </dl>
    </section>
</div>
