@props(['line', 'rank' => null, 'showBlocks' => false])
<article x-data="{ inspected: '' }" {{ $attributes->class('group/bid-line max-h-[150px] rounded-xl border border-slate-200 bg-white p-2 has-[[data-bid-expandable][open]]:max-h-none dark:border-slate-700 dark:bg-slate-900') }}>
    <div class="relative -mx-2 grid min-h-11 grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-2 bg-white px-2 dark:bg-slate-900 sm:gap-3 group-has-[[data-bid-expandable][open]]/bid-line:sticky group-has-[[data-bid-expandable][open]]/bid-line:top-[var(--crew-bids-header-height,0px)] group-has-[[data-bid-expandable][open]]/bid-line:z-20 group-has-[[data-bid-expandable][open]]/bid-line:shadow-sm">
        <div>
            <h3 @class(['font-mono text-base font-bold', 'text-orange-700 dark:text-orange-300' => $line['type'] === 'reserve', 'text-[#1B365D] dark:text-blue-200' => $line['type'] === 'flying'])>{{ $line['id'] }}<span class="sr-only"> · {{ $line['type'] === 'reserve' ? 'Reserve' : 'Flying' }} line</span></h3>
        </div>
        <dl class="flex min-w-0 items-center justify-end gap-2 text-[10px] leading-4 sm:justify-start sm:gap-6">
            @if (isset($line['credit']))<div class="min-w-0"><dt class="text-slate-500 dark:text-slate-400">Credit</dt><dd class="truncate font-mono text-xs font-semibold" title="{{ $line['credit_label'] }} · adjusted credit, h:mm[:ss]">{{ $line['credit_label'] }}</dd></div>@endif
            @if (isset($line['block']))<div class="min-w-0"><dt class="text-slate-500 dark:text-slate-400">Block</dt><dd class="truncate font-mono text-xs font-semibold" title="{{ $line['block_label'] }} · block, h:mm">{{ $line['block_label'] }}</dd></div>@endif
            @if (isset($line['days_off']))<div class="shrink-0"><dt class="whitespace-nowrap text-slate-500 dark:text-slate-400">Days off</dt><dd class="font-mono text-xs font-semibold">{{ $line['days_off'] }}</dd></div>@endif
        </dl>
        <div>
            @if ($rank !== null)
                <button type="button" wire:click="$set('showList', true)" aria-label="Selected #{{ $rank }}; open my bid list" class="inline-flex min-h-11 items-center gap-1 rounded-lg px-2 text-xs font-semibold focus-visible:ring-2 focus-visible:ring-amber-500"><x-heroicon-o-check class="h-4 w-4 shrink-0" aria-hidden="true" /><span><span class="hidden sm:inline">Selected </span>#{{ $rank }}</span></button>
            @else
                <button type="button" wire:click="add('{{ $line['id'] }}')" wire:loading.attr="disabled" aria-label="Add {{ $line['id'] }} to my bid list" class="inline-flex min-h-11 items-center gap-1 rounded-lg bg-[#1B365D] px-3 text-xs font-semibold text-white focus-visible:ring-2 focus-visible:ring-amber-500 disabled:opacity-50"><x-heroicon-o-plus class="h-4 w-4 shrink-0" aria-hidden="true" /><span>Add</span></button>
            @endif
        </div>
    </div>
    @if ($line['warnings'] !== [])<p class="mt-1 truncate text-xs leading-4 font-medium text-amber-800 dark:text-amber-300">{{ implode(' · ', $line['warnings']) }}</p>@endif
    <div class="mt-1 flex items-stretch gap-1">
    <button type="button" class="grid min-h-8 min-w-0 flex-1 gap-px rounded-md focus-visible:ring-2 focus-visible:ring-amber-500" style="grid-template-columns: repeat({{ count($line['month']) }}, minmax(0, 1fr))" aria-label="Inspect UTC dates for {{ $line['id'] }}; select a day marker, or open Line below" @click="inspected = $event.target.closest('[data-date-detail]')?.dataset.dateDetail ?? 'Open Line to inspect each date.'; $refs.dateDetails.open = true">
        @foreach ($line['month'] as $date => $entry)
            <span data-date-detail="{{ $date }} UTC · {{ $entry['activity'] }}" @class(['flex min-w-0 items-center justify-center rounded-sm font-mono text-[9px]', 'bg-[#1B365D] text-white dark:bg-blue-300 dark:text-slate-950' => $entry['status'] === 'occupied' && $line['type'] === 'flying', 'bg-orange-600 text-white dark:bg-orange-400 dark:text-slate-950' => $entry['status'] === 'occupied' && $line['type'] === 'reserve', 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' => $entry['status'] === 'off', 'bg-amber-200 text-amber-950' => $entry['status'] === 'unknown'])>{{ $entry['status'] === 'unknown' ? '?' : substr($date, 8) }}</span>
        @endforeach
    </button>
    @if ($line['carry_days'] > 0)
        <span class="flex shrink-0 items-center justify-center rounded-md border border-[#C5A059] bg-amber-50 px-2 font-mono text-xs font-semibold text-[#1B365D] dark:bg-amber-950 dark:text-amber-200" aria-label="{{ $line['carry_days'] }} carry-in days after month-end for {{ $line['id'] }}">+{{ $line['carry_days'] }}</span>
    @endif
    </div>
    <div class="mt-1 flex items-start gap-3 text-xs has-[>details[open]]:grid has-[>details[open]]:grid-cols-1">
    <details data-bid-expandable x-ref="dateDetails" class="min-w-0">
        <summary class="min-h-7 cursor-pointer whitespace-nowrap rounded-md py-1.5 text-left focus-visible:ring-2 focus-visible:ring-amber-500"><span class="inline-flex items-center gap-1 align-top"><x-heroicon-o-calendar-days class="h-3.5 w-3.5 shrink-0" aria-hidden="true" /><span>Line<span class="sr-only"> · UTC dates</span></span></span></summary>
        <p x-show="inspected" x-text="inspected" x-cloak role="status" class="mt-2 text-xs"></p>
        <div class="mt-2 flex flex-wrap gap-2">
            @foreach ($line['month'] as $date => $entry)
                <span class="rounded-md border border-slate-200 px-2 py-1 text-xs dark:border-slate-700"><span class="font-mono">{{ $date }}</span> · {{ $entry['activity'] }}</span>
            @endforeach
        </div>
    </details>
    @if ($line['trip_details'] !== [])
        <details data-bid-expandable class="min-w-0">
            <summary class="min-h-7 cursor-pointer truncate rounded-md py-1.5 text-left font-mono font-semibold text-[#1B365D] focus-visible:ring-2 focus-visible:ring-amber-500 dark:text-blue-200"><span class="inline-flex max-w-full items-center gap-1 align-top"><x-heroicon-o-paper-airplane class="h-3.5 w-3.5 shrink-0" aria-hidden="true" /><span class="truncate">{{ count($line['trip_details']) === 1 ? 'Trip '.$line['trip_details'][0]['id'] : 'Trips ('.count($line['trip_details']).')' }}</span></span></summary>
            <div class="mt-2 space-y-2" aria-label="Trips for {{ $line['id'] }}">
            @foreach ($line['trip_details'] as $trip)
                <details @if (count($line['trip_details']) === 1) open @endif class="rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-700">
                    <summary class="min-h-11 cursor-pointer rounded-md text-xs focus-visible:ring-2 focus-visible:ring-amber-500">
                        <span class="inline-block rounded bg-[#1B365D]/10 px-2 py-1 font-mono font-semibold text-[#1B365D] dark:bg-blue-300/10 dark:text-blue-200">Trip {{ $trip['id'] }}</span>
                        @if ($trip['available'])
                            <span class="ms-2 font-mono">{{ $trip['start_city'] }} → {{ $trip['end_city'] }}</span>
                            @if ($trip['layovers'] !== [])<span class="ms-2">Layovers: <span class="font-mono">{{ $trip['layover_label'] }}</span></span>@endif
                            @if ($trip['minimum_rest'] !== null)<span class="ms-2">Min rest <span class="font-mono">{{ $trip['minimum_rest'] }}</span> (h:mm)</span>@endif
                        @endif
                    </summary>
                    @if ($trip['available'])<x-crew-bids.trip-details :trip="$trip" />@endif
                </details>
            @endforeach
            </div>
        </details>
    @endif
    <details data-bid-expandable class="ms-auto min-w-0 open:ms-0">
        <summary class="min-h-7 cursor-pointer whitespace-nowrap rounded-md py-1.5 text-right font-medium underline underline-offset-4 focus-visible:ring-2 focus-visible:ring-amber-500"><span class="inline-flex items-center gap-1 align-top"><x-heroicon-o-document-text class="h-3.5 w-3.5 shrink-0" aria-hidden="true" /><span>Details</span></span></summary>
        @foreach ($line['warnings'] as $warning)<p class="mt-2 text-sm font-medium text-amber-800 dark:text-amber-300">{{ $warning }}</p>@endforeach
        <dl class="mt-3 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
            @if ($showBlocks && isset($line['blocks']))<div><dt class="text-slate-500 dark:text-slate-400">Work blocks</dt><dd class="font-mono">{{ $line['blocks'] }}</dd></div>@endif
            @if (isset($line['duty_days']))<div><dt class="text-slate-500 dark:text-slate-400">Duty days (source)</dt><dd class="font-mono">{{ $line['duty_days'] }}</dd></div>@endif
            @if (filled($line['start_city']))<div><dt class="text-slate-500 dark:text-slate-400">Start city</dt><dd class="font-mono">{{ $line['start_city'] }}</dd></div>@endif
            @if (filled($line['crew']))<div><dt class="text-slate-500 dark:text-slate-400">Crew complement</dt><dd>{{ $line['crew'] }}</dd></div>@endif
            @if (isset($line['carry_days']))<div><dt class="text-slate-500 dark:text-slate-400">Carry-in days after month-end</dt><dd class="font-mono">{{ $line['carry_days'] }}</dd></div>@endif
            @foreach ([
                'source_credit_label' => 'Lines / Overview credit · h:mm',
                'source_block_label' => 'Lines block · h:mm',
                'base_credit_label' => 'Base credit · h:mm',
                'deadhead_credit_label' => 'Deadhead credit · 50% elapsed · h:mm[:ss]',
                'pch_label' => 'PCH · carry-in credit before premium · h:mm[:ss]',
                'carry_flying_label' => 'Carry-in flying · h:mm[:ss]',
                'carry_deadhead_credit_label' => 'Carry-in deadhead credit · h:mm[:ss]',
                'carry_premium_label' => 'Extra 50% carry-in credit · h:mm[:ss]',
            ] as $field => $label)
                @if ($line[$field] !== 'Not available')<div><dt class="text-slate-500 dark:text-slate-400">{{ $label }}</dt><dd class="font-mono">{{ $line[$field] }}</dd></div>@endif
            @endforeach
        </dl>
        @if (isset($line['credit']) || isset($line['block']))
            <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                @if (isset($line['credit']))Base credit: {{ ($line['credit_basis'] ?? '') === 'trip_totals' ? 'Complete pairing Credit Time totals from Trips, including credited deadheads.' : 'Recorded line credit.' }}@endif
                @if (isset($line['block']))Block: {{ ($line['block_basis'] ?? '') === 'trip_totals' ? 'Complete pairing Block Time totals from Trips.' : 'Recorded Lines block time.' }}@endif
                @if (isset($line['pch_seconds']))PCH counts legs departing in the next month in UTC: flying at 100% and deadheads at 50%. Adjusted credit adds only an extra 50% of PCH to base credit. Flights departing before month-end and ground transfers contribute no PCH. Seconds preserve fractional minutes.@endif
            </p>
        @endif
        <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">Days off and work blocks use UTC occupancy, including trip layovers. Duty days are the separate source count.</p>
    </details>
    @if ($line['match_details'] !== [] || $line['filter_details'] !== [])
        <details data-bid-expandable class="min-w-0">
            <summary aria-label="Why this matched" title="Why this matched" class="ms-auto flex h-7 w-7 cursor-pointer list-none items-center justify-center rounded-md p-1 text-slate-600 focus-visible:ring-2 focus-visible:ring-amber-500 dark:text-slate-300 [&::-webkit-details-marker]:hidden"><x-heroicon-o-question-mark-circle class="h-5 w-5 shrink-0" aria-hidden="true" /><span class="sr-only">Why this matched</span></summary>
            <div class="mt-2 rounded-lg border border-slate-200 p-3 text-xs dark:border-slate-700">
                @if ($line['match_details'] !== [])
                    <p>Priorities in order; each lower goal breaks ties only.</p>
                    <ol class="mt-2 list-inside list-decimal space-y-1">@foreach ($line['match_details'] as $detail)<li>{{ $detail }}</li>@endforeach</ol>
                @endif
                @foreach ($line['filter_details'] as $detail)<p class="mt-2">{{ $detail }}</p>@endforeach
                <p class="mt-2 text-slate-500 dark:text-slate-400">Browsing sort can change display order. Your bid order stays manual.</p>
            </div>
        </details>
    @endif
    </div>
</article>
