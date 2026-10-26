@props(['line', 'rank' => null, 'showBlocks' => false])
<article {{ $attributes->class('rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900') }}>
    <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
        <h3 @class(['font-mono text-lg font-bold', 'text-orange-700 dark:text-orange-300' => $line['type'] === 'reserve', 'text-[#1B365D] dark:text-blue-200' => $line['type'] === 'flying'])>{{ $line['id'] }}<span class="sr-only"> · {{ $line['type'] === 'reserve' ? 'Reserve' : 'Flying' }} line</span></h3>
        <span class="text-sm">Credit <span class="font-mono font-semibold">{{ $line['credit_label'] }}</span></span>
        <span class="text-sm">Days off <span class="font-mono font-semibold">{{ $line['days_off'] ?? 'Not available' }}</span></span>
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
    <button type="button" class="grid min-h-11 w-full gap-px rounded-md focus-visible:ring-2 focus-visible:ring-amber-500" style="grid-template-columns: repeat({{ count($line['month']) }}, minmax(0, 1fr))" aria-label="Inspect UTC dates for {{ $line['id'] }}; select a day marker, or open All dates below" @click="inspected = $event.target.closest('[data-date-detail]')?.dataset.dateDetail ?? 'Open All dates to inspect each date.'">
        @foreach ($line['month'] as $date => $entry)
            <span data-date-detail="{{ $date }} UTC · {{ $entry['activity'] }}" @class(['flex min-w-0 items-center justify-center rounded-sm font-mono text-[9px]', 'bg-[#1B365D] text-white dark:bg-blue-300 dark:text-slate-950' => $entry['status'] === 'occupied' && $line['type'] === 'flying', 'bg-orange-600 text-white dark:bg-orange-400 dark:text-slate-950' => $entry['status'] === 'occupied' && $line['type'] === 'reserve', 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' => $entry['status'] === 'off', 'bg-amber-200 text-amber-950' => $entry['status'] === 'unknown'])>{{ $entry['status'] === 'unknown' ? '?' : substr($date, 8) }}</span>
        @endforeach
    </button>
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
    <details class="mt-3">
        <summary class="inline-block min-h-8 cursor-pointer rounded-md text-sm font-medium underline underline-offset-4 focus-visible:ring-2 focus-visible:ring-amber-500">Details</summary>
        <dl class="mt-3 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
            <div><dt class="text-slate-500 dark:text-slate-400">Duty days (source)</dt><dd class="font-mono">{{ $line['duty_days'] ?? 'Not available' }}</dd></div>
            <div><dt class="text-slate-500 dark:text-slate-400">Start city</dt><dd class="font-mono">{{ $line['start_city'] ?? 'Not available' }}</dd></div>
            <div><dt class="text-slate-500 dark:text-slate-400">Crew complement</dt><dd>{{ $line['crew'] }}</dd></div>
            <div><dt class="text-slate-500 dark:text-slate-400">Carry-in (source)</dt><dd class="font-mono">{{ $line['carry_in'] ?? 'Not available' }}</dd></div>
        </dl>
        @if ($line['pairings'] !== [])
            <p class="mt-3 text-sm">Trips: @foreach ($line['pairings'] as $pairing)<span class="font-mono">{{ $pairing['id'] }} ({{ $pairing['date'] }})</span>{{ $loop->last ? '' : ' · ' }}@endforeach</p>
        @endif
        <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">Days off and work blocks use UTC occupancy, including trip layovers. Duty days are the separate source count.</p>
    </details>
</article>
