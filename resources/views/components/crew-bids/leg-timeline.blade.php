@props(['trip'])
<section {{ $attributes->class('min-w-0 rounded-lg border border-slate-200 p-3 dark:border-slate-700') }} aria-label="Visual leg timeline">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h5 class="font-semibold">Leg timeline · UTC</h5>
        <span class="font-mono text-xs text-slate-500 dark:text-slate-400">{{ $trip['start_date'] }} → {{ $trip['end_date'] }}</span>
    </div>
    @if ($trip['legs'] !== [])
        <p class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-600 dark:text-slate-300">
            <span class="flex items-center gap-1"><span aria-hidden="true" class="h-2 w-4 rounded-sm bg-[#1B365D] dark:bg-blue-300"></span>Flying</span>
            <span class="flex items-center gap-1"><span aria-hidden="true" class="h-2 w-4 rounded-sm bg-slate-200 text-slate-500 dark:bg-slate-700 dark:text-slate-300" style="background-image: repeating-linear-gradient(135deg, transparent, transparent 3px, currentColor 3px, currentColor 4px)"></span>Deadhead</span>
            <span class="flex items-center gap-1"><span aria-hidden="true" class="h-2 w-4 rounded-sm border border-slate-400"></span>Ground transfer</span>
            <span class="flex items-center gap-1"><span aria-hidden="true" class="h-2 w-4 rounded-sm bg-amber-200 dark:bg-amber-800"></span>Layover</span>
        </p>
        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Follow the trip left to right. Scroll for more legs; open a chevron for exact times. Segment widths are illustrative.</p>
        <div role="region" aria-label="Leg timeline for trip {{ $trip['id'] }}, UTC" tabindex="0" class="mt-3 max-w-full overflow-x-auto rounded-md pb-3 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-500">
            <ol class="flex w-max items-start gap-2 p-1">
                @foreach ($trip['legs'] as $leg)
                    <li class="w-40 shrink-0">
                        <p class="mb-2 font-mono text-xs text-slate-500 dark:text-slate-400">{{ $leg['date'] }}</p>
                        <details class="rounded-md focus-within:ring-2 focus-within:ring-amber-500">
                            <summary class="min-h-11 cursor-pointer list-none rounded-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-500 [&::-webkit-details-marker]:hidden">
                                <span @class(['block px-5 py-3 text-center', 'bg-[#1B365D] text-white dark:bg-blue-300 dark:text-slate-950' => $leg['timeline_type'] === 'flying', 'bg-slate-200 text-slate-800 dark:bg-slate-700 dark:text-slate-100' => $leg['timeline_type'] === 'deadhead', 'bg-slate-100 text-slate-700 ring-1 ring-inset ring-slate-400 dark:bg-slate-800 dark:text-slate-200' => $leg['timeline_type'] === 'ground']) style="clip-path: polygon(0 0, calc(100% - 12px) 0, 100% 50%, calc(100% - 12px) 100%, 0 100%, 12px 50%); {{ $leg['timeline_type'] === 'deadhead' ? 'background-image: repeating-linear-gradient(135deg, transparent, transparent 6px, rgb(100 116 139 / 0.25) 6px, rgb(100 116 139 / 0.25) 9px)' : '' }}">
                                    <span class="block text-[10px] font-semibold uppercase tracking-wide">{{ $leg['timeline_label'] }}</span>
                                    <span class="mt-1 block font-mono text-sm font-bold">{{ $leg['origin'] }} → {{ $leg['destination'] }}</span>
                                    <span class="mt-1 block font-mono text-xs">@if ($leg['deadhead'])Credit {{ $leg['deadhead_credit_label'] }}@elseif (isset($leg['block_duration_label']))Block {{ $leg['block_duration_label'] }}@else Elapsed {{ $leg['elapsed_label'] }}@endif</span>
                                </span>
                                <span class="mt-2 block px-1 font-mono text-xs">{{ $leg['departure'] }} → {{ $leg['arrival'] }} UTC</span>
                                @if ($leg['arrival_date'] !== $leg['date'])<span class="block px-1 font-mono text-[10px]">Arrives {{ $leg['arrival_date'] }}</span>@endif
                            </summary>
                            <dl class="mt-2 space-y-2 rounded-md bg-slate-50 p-2 text-xs dark:bg-slate-800">
                                @if ($leg['flight'] ?? null)<div><dt>Flight / transport</dt><dd class="font-mono">{{ $leg['flight'] }}</dd></div>@endif
                                <div><dt>Departure · UTC</dt><dd class="font-mono">{{ $leg['date'] }} {{ $leg['departure'] }}</dd></div>
                                <div><dt>Arrival · UTC</dt><dd class="font-mono">{{ $leg['arrival_date'] }} {{ $leg['arrival'] }}</dd></div>
                                @if (isset($leg['block']))<div><dt>Block · h:mm</dt><dd class="font-mono">{{ $leg['block'] }}@if ($leg['block_basis'] === 'calculated') · calculated from scheduled UTC times @else · source @endif</dd></div>@endif
                                <div><dt>Scheduled elapsed</dt><dd class="font-mono">{{ $leg['elapsed_label'] }}</dd></div>
                                @if ($leg['deadhead'])<div><dt>Credit · 50% elapsed · h:mm[:ss]</dt><dd class="font-mono">{{ $leg['deadhead_credit_label'] }}</dd></div>@endif
                                @if ($leg['rest_after'] ?? null)<div><dt>Source rest · h:mm</dt><dd class="font-mono">{{ $leg['rest_after'] }}</dd></div>@endif
                            </dl>
                        </details>
                    </li>
                    @if ($leg['connection_status'] !== null)
                        <li class="w-36 shrink-0">
                            <p class="mb-2 font-mono text-xs text-slate-500 dark:text-slate-400">{{ $leg['arrival_date'] }}</p>
                            @if ($leg['connection_status'] === 'layover')
                                <details @class(['rounded-md border focus-within:ring-2 focus-within:ring-amber-500', 'border-amber-400 bg-amber-50 text-amber-950 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-100' => $leg['layover_highlight'] === 'short', 'border-blue-300 bg-blue-50 text-[#1B365D] dark:border-blue-700 dark:bg-blue-950 dark:text-blue-200' => $leg['layover_highlight'] === 'extended', 'border-[#C5A059]/50 bg-amber-50 text-amber-950 dark:bg-amber-950 dark:text-amber-100' => $leg['layover_highlight'] === null])>
                                    <summary class="min-h-11 cursor-pointer rounded-md px-3 py-3 text-center focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-500">
                                        <span class="block text-[10px] font-semibold uppercase tracking-wide">Layover</span>
                                        <span class="mt-1 block font-mono text-sm font-bold">{{ $leg['destination'] }}</span>
                                        <span class="mt-1 block font-mono text-xs">{{ $leg['layover_label'] }}</span>
                                        @if ($leg['layover_highlight'] === 'short')<span class="mt-2 inline-flex rounded-full border border-amber-300 bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-900 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-200">Short layover · under 10h</span>
                                        @elseif ($leg['layover_highlight'] === 'extended')<span class="mt-2 inline-flex rounded-full border border-blue-300 bg-blue-100 px-2 py-0.5 text-[10px] font-semibold text-[#1B365D] dark:border-blue-700 dark:bg-blue-950 dark:text-blue-200">Extended layover · 24h+</span>@endif
                                    </summary>
                                    <div class="space-y-1 border-t border-[#C5A059]/30 p-2 text-xs">
                                        <p>Arrival → next departure · UTC</p>
                                        <p class="font-mono">{{ $leg['arrival_date'] }} {{ $leg['arrival'] }} → {{ $leg['connection_until'] }}</p>
                                        @if ($leg['rest_after'] ?? null)<p>Source rest: <span class="font-mono">{{ $leg['rest_after'] }}</span> (h:mm)</p>@endif
                                    </div>
                                </details>
                            @else
                                <div class="rounded-md border border-dashed border-amber-500 p-3 text-xs text-amber-900 dark:text-amber-200">
                                    <p class="font-semibold">Connection uncertain</p>
                                    <p class="mt-1">{{ $leg['connection_status'] === 'overlap' ? 'Consecutive leg times overlap.' : 'Next leg uses a different airport.' }}</p>
                                </div>
                            @endif
                        </li>
                    @endif
                @endforeach
            </ol>
        </div>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Durations use scheduled elapsed time. Layovers run from arrival to next departure; source rest is separate.</p>
    @else
        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">A leg timeline is unavailable because no dated legs were imported.</p>
    @endif
</section>
