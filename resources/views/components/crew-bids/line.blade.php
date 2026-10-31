@props(['line'])
<article x-data="{ inspected: '' }" {{ $attributes->class('group/bid-line max-h-[150px] rounded-xl border border-slate-200 bg-white p-2 has-[[data-bid-expandable][open]]:max-h-none dark:border-slate-700 dark:bg-slate-900') }}>
    <div class="relative -mx-2 grid min-h-11 grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-2 bg-white px-2 dark:bg-slate-900 sm:gap-3 group-has-[[data-bid-expandable][open]]/bid-line:sticky group-has-[[data-bid-expandable][open]]/bid-line:top-[var(--crew-bids-header-height,0px)] group-has-[[data-bid-expandable][open]]/bid-line:z-20 group-has-[[data-bid-expandable][open]]/bid-line:shadow-sm">
        <div>
            <h3 class="font-mono text-base font-bold {{ $line->headingClasses }}">{{ $line->id }}<span class="sr-only"> · {{ $line->typeLabel }} line</span></h3>
            @if ($line->crew !== null)<span class="block whitespace-nowrap font-mono text-[10px] leading-4 text-slate-600 dark:text-slate-300" aria-label="Crew complement: {{ $line->crew }}">{{ $line->crew }}</span>@endif
            @if ($line->quickFlags !== [])
                <div role="group" class="flex items-center gap-0.5" aria-label="Trip flags for {{ $line->id }}">
                    @foreach ($line->quickFlags as $flag)
                        <span role="img" aria-label="{{ $flag['description'] }}" title="{{ $flag['description'] }}" class="inline-flex h-4 items-center gap-1 rounded px-0.5 text-[9px] leading-none {{ $flag['classes'] }}">
                            <x-dynamic-component :component="$flag['icon']" class="h-3 w-3 shrink-0" aria-hidden="true" />
                            <span @class(['hidden sm:inline' => ! $flag['showLabelOnMobile']]) aria-hidden="true">{{ $flag['label'] }}</span>
                        </span>
                    @endforeach
                </div>
            @endif
        </div>
        <dl class="flex min-w-0 items-center justify-end gap-2 text-[10px] leading-4 sm:justify-start sm:gap-6">
            @foreach ($line->summaryMetrics as $metric)
                <div class="{{ $metric['containerClasses'] }}"><dt class="text-slate-500 dark:text-slate-400 {{ $metric['labelClasses'] }}">{{ $metric['label'] }}</dt><dd class="{{ $metric['valueClasses'] }}" @if ($metric['title'] !== null) title="{{ $metric['title'] }}" @endif>{{ $metric['value'] }}</dd></div>
            @endforeach
        </dl>
        <div>
            @if ($line->rank !== null)
                <button type="button" @click="$wire.showList = true" aria-label="Selected #{{ $line->rank }}; open my bid list" class="inline-flex min-h-11 items-center gap-1 rounded-lg px-2 text-xs font-semibold focus-visible:ring-2 focus-visible:ring-amber-500"><x-heroicon-o-check class="h-4 w-4 shrink-0" aria-hidden="true" /><span><span class="hidden sm:inline">Selected </span>#{{ $line->rank }}</span></button>
            @else
                <button type="button" x-data="bidAction" @click="run(() => $wire.add('{{ $line->id }}'))" :disabled="pending" :aria-busy="pending" wire:loading.attr="disabled" wire:target="add,remove,move,addMatching,clearList,undoAdd,changeContext,confirmRevision" aria-label="Add {{ $line->id }} to my bid list" class="inline-flex min-h-11 items-center gap-1 rounded-lg bg-[#1B365D] px-3 text-xs font-semibold text-white focus-visible:ring-2 focus-visible:ring-amber-500 disabled:opacity-50"><x-heroicon-o-plus x-show="! pending" class="h-4 w-4 shrink-0" aria-hidden="true" /><x-heroicon-o-arrow-path x-show="pending" x-cloak class="h-4 w-4 shrink-0 animate-spin motion-reduce:animate-none" aria-hidden="true" /><span x-text="pending ? 'Adding…' : 'Add'" role="status">Add</span></button>
            @endif
        </div>
    </div>
    @if ($line->warnings !== [])<p class="mt-1 truncate text-xs leading-4 font-medium text-amber-800 dark:text-amber-300">{{ $line->warningSummary }}</p>@endif
    <div class="mt-1 flex items-stretch gap-1">
    <button type="button" class="grid min-h-8 min-w-0 flex-1 gap-px rounded-md focus-visible:ring-2 focus-visible:ring-amber-500" style="grid-template-columns: repeat({{ count($line->calendarDays) }}, minmax(0, 1fr))" aria-label="Inspect UTC dates for {{ $line->id }}; select a day marker, or open Line below" @click="inspected = $event.target.closest('[data-date-detail]')?.dataset.dateDetail ?? 'Open Line to inspect each date.'; $refs.dateDetails.open = true">
        @foreach ($line->calendarDays as $day)
            <span data-date-detail="{{ $day['description'] }}" class="flex min-w-0 items-center justify-center rounded-sm font-mono text-[9px] {{ $day['classes'] }}">{{ $day['label'] }}</span>
        @endforeach
    </button>
    @if ($line->carryDays > 0)
        <span class="flex shrink-0 items-center justify-center rounded-md border border-[#C5A059] bg-amber-50 px-2 font-mono text-xs font-semibold text-[#1B365D] dark:bg-amber-950 dark:text-amber-200" aria-label="{{ $line->carryDays }} carry-in days after month-end for {{ $line->id }}">+{{ $line->carryDays }}</span>
    @endif
    </div>
    <div class="mt-1 flex items-start gap-3 text-xs has-[>details[open]]:grid has-[>details[open]]:grid-cols-1">
    <details data-bid-expandable x-ref="dateDetails" class="min-w-0">
        <summary class="min-h-7 cursor-pointer whitespace-nowrap rounded-md py-1.5 text-left focus-visible:ring-2 focus-visible:ring-amber-500"><span class="inline-flex items-center gap-1 align-top"><x-heroicon-o-calendar-days class="h-3.5 w-3.5 shrink-0" aria-hidden="true" /><span>Line<span class="sr-only"> · UTC dates</span></span></span></summary>
        <p x-show="inspected" x-text="inspected" x-cloak role="status" class="mt-2 text-xs"></p>
        <div class="mt-2 flex flex-wrap gap-2">
            @foreach ($line->calendarDays as $day)
                <span class="rounded-md border border-slate-200 px-2 py-1 text-xs dark:border-slate-700"><span class="font-mono">{{ $day['date'] }}</span> · {{ $day['activity'] }}</span>
            @endforeach
        </div>
    </details>
    @if ($line->trips !== [])
        <details data-bid-expandable class="min-w-0">
            <summary class="min-h-7 cursor-pointer truncate rounded-md py-1.5 text-left font-mono font-semibold text-[#1B365D] focus-visible:ring-2 focus-visible:ring-amber-500 dark:text-blue-200"><span class="inline-flex max-w-full items-center gap-1 align-top"><x-heroicon-o-paper-airplane class="h-3.5 w-3.5 shrink-0" aria-hidden="true" /><span class="truncate">{{ $line->tripSummaryLabel }}</span></span></summary>
            <div class="mt-2 space-y-2" aria-label="Trips for {{ $line->id }}">
            @foreach ($line->trips as $trip)
                <details @if ($line->singleTrip) open @endif class="rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-700">
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
        @foreach ($line->warnings as $warning)<p class="mt-2 text-sm font-medium text-amber-800 dark:text-amber-300">{{ $warning }}</p>@endforeach
        @if ($line->quickFlags !== [])
            <ul class="mt-2 space-y-1 text-xs" aria-label="Trip flag explanations for {{ $line->id }}">
                @foreach ($line->quickFlags as $flag)<li>{{ $flag['description'] }}</li>@endforeach
            </ul>
        @endif
        <dl class="mt-3 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
            @foreach ($line->detailMetrics as $metric)
                <div><dt class="text-slate-500 dark:text-slate-400">{{ $metric['label'] }}</dt><dd class="{{ $metric['valueClasses'] }}">{{ $metric['value'] }}</dd></div>
            @endforeach
        </dl>
        @if ($line->creditNotes !== [])
            <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                @foreach ($line->creditNotes as $note){{ $note }} @endforeach
            </p>
        @endif
        <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">Days off and work blocks use UTC occupancy, including trip layovers. Duty days are the separate source count.</p>
    </details>
    @if ($line->matchDetails !== [] || $line->filterDetails !== [])
        <details data-bid-expandable class="min-w-0">
            <summary aria-label="Why this matched" title="Why this matched" class="ms-auto flex h-7 w-7 cursor-pointer list-none items-center justify-center rounded-md p-1 text-slate-600 focus-visible:ring-2 focus-visible:ring-amber-500 dark:text-slate-300 [&::-webkit-details-marker]:hidden"><x-heroicon-o-question-mark-circle class="h-5 w-5 shrink-0" aria-hidden="true" /><span class="sr-only">Why this matched</span></summary>
            <div class="mt-2 rounded-lg border border-slate-200 p-3 text-xs dark:border-slate-700">
                @if ($line->matchDetails !== [])
                    <p>Priorities in order; each lower goal breaks ties only.</p>
                    <ol class="mt-2 list-inside list-decimal space-y-1">@foreach ($line->matchDetails as $detail)<li>{{ $detail }}</li>@endforeach</ol>
                @endif
                @foreach ($line->filterDetails as $detail)<p class="mt-2">{{ $detail }}</p>@endforeach
                <p class="mt-2 text-slate-500 dark:text-slate-400">Browsing sort can change display order. Your bid order stays manual.</p>
            </div>
        </details>
    @endif
    </div>
</article>
