@props(['line', 'rank' => null, 'showBlocks' => false])
<article {{ $attributes->class('rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900') }}>
    <div class="relative flex flex-wrap items-center gap-x-4 gap-y-2">
        <h3 @class(['font-mono text-lg font-bold', 'text-orange-700 dark:text-orange-300' => $line['type'] === 'reserve', 'text-[#1B365D] dark:text-blue-200' => $line['type'] === 'flying'])>{{ $line['id'] }}<span class="sr-only"> · {{ $line['type'] === 'reserve' ? 'Reserve' : 'Flying' }} line</span></h3>
        <span class="text-sm">Credit <span class="font-mono font-semibold">{{ $line['credit_label'] }}</span></span>
        <span class="text-sm">Days off <span class="font-mono font-semibold">{{ $line['days_off'] ?? 'Not available' }}</span></span>
        @if ($line['match_details'] !== [] || $line['filter_details'] !== [])
            <details class="sm:relative">
                <summary class="min-h-11 cursor-pointer rounded-md py-3 text-xs text-slate-600 underline underline-offset-4 focus-visible:ring-2 focus-visible:ring-amber-500 dark:text-slate-300">Why this matched</summary>
                <div class="absolute start-0 top-full z-20 w-56 max-w-[75vw] rounded-lg border border-slate-200 bg-white p-3 text-xs shadow-lg dark:border-slate-700 dark:bg-slate-900 sm:start-auto sm:end-0">
                    @if ($line['match_details'] !== [])
                        <p>Priorities in order; each lower goal breaks ties only.</p>
                        <ol class="mt-2 list-inside list-decimal space-y-1">@foreach ($line['match_details'] as $detail)<li>{{ $detail }}</li>@endforeach</ol>
                    @endif
                    @foreach ($line['filter_details'] as $detail)<p class="mt-2">{{ $detail }}</p>@endforeach
                    <p class="mt-2 text-slate-500 dark:text-slate-400">Browsing sort can change display order. Your bid order stays manual.</p>
                </div>
            </details>
        @endif
        @if ($showBlocks)<span class="text-sm">Blocks <span class="font-mono">{{ $line['blocks'] ?? 'Unknown' }}</span></span>@endif
        <div class="ms-auto">
            @if ($rank !== null)
                <button type="button" wire:click="$set('showList', true)" class="min-h-11 rounded-lg px-3 text-sm font-semibold focus-visible:ring-2 focus-visible:ring-amber-500">Selected #{{ $rank }}</button>
            @else
                <button type="button" wire:click="add('{{ $line['id'] }}')" wire:loading.attr="disabled" aria-label="Add {{ $line['id'] }} to my bid list" class="min-h-11 rounded-lg bg-[#1B365D] px-4 text-sm font-semibold text-white focus-visible:ring-2 focus-visible:ring-amber-500 disabled:opacity-50">Add</button>
            @endif
        </div>
    </div>
    @foreach ($line['warnings'] as $warning)<p class="mt-2 text-sm font-medium text-amber-800 dark:text-amber-300">{{ $warning }}</p>@endforeach
    <div class="mt-3" x-data="{ inspected: '' }">
    <div class="flex items-stretch gap-1">
    <button type="button" class="grid min-h-11 min-w-0 flex-1 gap-px rounded-md focus-visible:ring-2 focus-visible:ring-amber-500" style="grid-template-columns: repeat({{ count($line['month']) }}, minmax(0, 1fr))" aria-label="Inspect UTC dates for {{ $line['id'] }}; select a day marker, or open All dates below" @click="inspected = $event.target.closest('[data-date-detail]')?.dataset.dateDetail ?? 'Open All dates to inspect each date.'">
        @foreach ($line['month'] as $date => $entry)
            <span data-date-detail="{{ $date }} UTC · {{ $entry['activity'] }}" @class(['flex min-w-0 items-center justify-center rounded-sm font-mono text-[9px]', 'bg-[#1B365D] text-white dark:bg-blue-300 dark:text-slate-950' => $entry['status'] === 'occupied' && $line['type'] === 'flying', 'bg-orange-600 text-white dark:bg-orange-400 dark:text-slate-950' => $entry['status'] === 'occupied' && $line['type'] === 'reserve', 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' => $entry['status'] === 'off', 'bg-amber-200 text-amber-950' => $entry['status'] === 'unknown'])>{{ $entry['status'] === 'unknown' ? '?' : substr($date, 8) }}</span>
        @endforeach
    </button>
    @if ($line['carry_days'] > 0)
        <span class="flex shrink-0 items-center justify-center rounded-md border border-[#C5A059] bg-amber-50 px-2 font-mono text-xs font-semibold text-[#1B365D] dark:bg-amber-950 dark:text-amber-200" aria-label="{{ $line['carry_days'] }} carry-in days after month-end for {{ $line['id'] }}">+{{ $line['carry_days'] }}</span>
    @endif
    </div>
    <p x-show="inspected" x-text="inspected" x-cloak role="status" class="mt-2 text-xs"></p>
    <details class="mt-1">
        <summary class="cursor-pointer rounded-md py-2 text-xs focus-visible:ring-2 focus-visible:ring-amber-500">All dates · UTC</summary>
        <div class="mt-2 flex flex-wrap gap-2">
            @foreach ($line['month'] as $date => $entry)
                <span class="rounded-md border border-slate-200 px-2 py-1 text-xs dark:border-slate-700"><span class="font-mono">{{ $date }}</span> · {{ $entry['activity'] }}</span>
            @endforeach
        </div>
    </details>
    </div>
    @if ($line['trip_details'] !== [])
        <div class="mt-3 space-y-2" aria-label="Trips for {{ $line['id'] }}">
            @foreach ($line['trip_details'] as $trip)
                <details class="rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-700">
                    <summary class="min-h-11 cursor-pointer rounded-md text-xs focus-visible:ring-2 focus-visible:ring-amber-500">
                        <span class="inline-block rounded bg-[#1B365D]/10 px-2 py-1 font-mono font-semibold text-[#1B365D] dark:bg-blue-300/10 dark:text-blue-200">Trip {{ $trip['id'] }}</span>
                        @if ($trip['available'])
                            <span class="ms-2 font-mono">{{ $trip['start_city'] }} → {{ $trip['end_city'] }}</span>
                            <span class="ms-2">Layovers: <span class="font-mono">{{ $trip['layover_label'] }}</span></span>
                            <span class="ms-2">Min rest <span class="font-mono">{{ $trip['minimum_rest'] ?? 'Not present' }}</span>{{ $trip['minimum_rest'] !== null ? ' (h:mm)' : '' }}</span>
                        @else
                            <span class="ms-2">Trip details not available</span>
                        @endif
                    </summary>
                    @if ($trip['available'])<x-crew-bids.trip-details :trip="$trip" />
                    @else<p class="mt-2 text-sm">Trips file not imported or this trip was not found in the imported file.</p>@endif
                </details>
            @endforeach
        </div>
    @elseif ($line['type'] === 'flying')
        <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">No linked trips available in the imported line data.</p>
    @endif
    <details class="mt-3">
        <summary class="inline-block min-h-8 cursor-pointer rounded-md text-sm font-medium underline underline-offset-4 focus-visible:ring-2 focus-visible:ring-amber-500">Details</summary>
        <dl class="mt-3 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
            <div><dt class="text-slate-500 dark:text-slate-400">Duty days (source)</dt><dd class="font-mono">{{ $line['duty_days'] ?? 'Not available' }}</dd></div>
            <div><dt class="text-slate-500 dark:text-slate-400">Start city</dt><dd class="font-mono">{{ $line['start_city'] ?? 'Not available' }}</dd></div>
            <div><dt class="text-slate-500 dark:text-slate-400">Crew complement</dt><dd>{{ $line['crew'] }}</dd></div>
            <div><dt class="text-slate-500 dark:text-slate-400">Carry-in days after month-end</dt><dd class="font-mono">{{ $line['carry_days'] ?? 'Not available' }}</dd></div>
        </dl>
        @foreach ($line['trip_details'] as $trip)
            @if ($trip['available'])
                <div class="mt-4 border-t border-slate-200 pt-3 dark:border-slate-700"><h4 class="font-mono text-sm font-semibold">Trip {{ $trip['id'] }}</h4><x-crew-bids.trip-details :trip="$trip" /></div>
            @endif
        @endforeach
        <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">Days off and work blocks use UTC occupancy, including trip layovers. Duty days are the separate source count.</p>
    </details>
</article>
