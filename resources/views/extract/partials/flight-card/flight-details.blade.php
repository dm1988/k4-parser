<div class="grid gap-2 sm:grid-cols-2">
    <div>
        <div class="flex items-center justify-between gap-3 rounded-lg border border-[#1B365D]/10 bg-white px-3 py-2">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-[#4A5568]">
                Flight Number
            </p>
            <p class="font-mono text-sm font-semibold text-[#0B0E14] text-right whitespace-nowrap">
                {{ $model->flight->flightNumber ?? '—' }}
            </p>
        </div>
    </div>
    <div>
        <div class="flex items-center justify-between gap-3 rounded-lg border border-[#1B365D]/10 bg-white px-3 py-2">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-[#4A5568]">
                Type
            </p>
            <p class="min-w-0 truncate text-sm font-semibold text-[#0B0E14] text-right">
                {{ $model->flight->typeLabel ?? '—' }}
            </p>
        </div>
    </div>
    
    <div class="grid grid-cols-2 gap-2 sm:col-span-2">
        <div class="flex items-center justify-between gap-3 rounded-lg border border-[#1B365D]/10 bg-white px-3 py-2">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-[#4A5568]">
                Departure
            </p>
            <p class="text-sm font-semibold text-[#0B0E14] text-right whitespace-nowrap">
                {{ $model->originTimeLabel() }}
            </p>
        </div>

        <div class="flex items-center justify-between gap-3 rounded-lg border border-[#1B365D]/10 bg-white px-3 py-2">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-[#4A5568]">
                Arrival
            </p>
            <p class="text-sm font-semibold text-[#0B0E14] text-right whitespace-nowrap">
                {{ $model->destinationTimeLabel() }}
            </p>
        </div>
    </div>

    @if ($model->hasLegLocalTimes())
    <div class="sm:col-span-2">
        <div class="flex items-center justify-between gap-3 rounded-lg border border-[#1B365D]/10 bg-white px-3 py-2">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-[#4A5568]">
                Flight Times (Local)
            </p>
            <p class="text-sm font-semibold text-[#0B0E14] text-right whitespace-nowrap">
                {{ $model->legLocalTimesLabel() }}
            </p>
        </div>
    </div>
    @endif

    @if ($model->hasDutyLocalTimes())
    <div class="sm:col-span-2">
        <div class="flex items-center justify-between gap-3 rounded-lg border border-[#1B365D]/10 bg-white px-3 py-2">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-[#4A5568]">
                Duty Times (Local)
            </p>
            <p class="text-sm font-semibold text-[#0B0E14] text-right whitespace-nowrap">
                {{ $model->dutyLocalTimesLabel() }}
            </p>
        </div>
    </div>
    @endif

    <div>
        <div class="flex items-center justify-between gap-3 rounded-lg border border-[#1B365D]/10 bg-white px-3 py-2">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-[#4A5568]">
                Duration
            </p>
            <p class="min-w-0 truncate text-sm font-semibold text-[#0B0E14] text-right">
                {{ $model->flight->durationLabel ?? '—' }}
            </p>
        </div>
    </div>
    <div>
        <div class="flex items-center justify-between gap-3 rounded-lg border border-[#1B365D]/10 bg-white px-3 py-2">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-[#4A5568]">
                Aircraft Type
            </p>
            <p class="min-w-0 truncate font-mono text-sm font-semibold text-[#0B0E14] text-right">
                {{ $model->flight->aircraft ?? '—' }}
            </p>
        </div>
    </div>
    <div>
        <div class="flex items-center justify-between gap-3 rounded-lg border border-[#1B365D]/10 bg-white px-3 py-2">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-[#4A5568]">
                Tail Number
            </p>
            <p class="min-w-0 truncate font-mono text-sm font-semibold text-[#0B0E14] text-right">
                {{ $model->flight->tailNumber ?? '—' }}
            </p>
        </div>
    </div>
    @if ( $model->flight->tailNumber )
    <div>
        <div class="flex items-center justify-between gap-3 rounded-lg border border-[#1B365D]/10 bg-white px-3 py-2">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-[#4A5568]">
                Flight Tracking
            </p>
            <a href="https://flightaware.com/live/flight/{{ $model->flight->tailNumber }}" target="_blank"
                rel="noopener noreferrer"
                class="inline-flex min-w-0 items-center gap-1 font-mono text-sm font-semibold text-[#1B365D] transition-colors hover:text-[#C5A059] group">
                <span class="underline decoration-transparent transition-all group-hover:decoration-[#C5A059]">Flight
                    Aware</span>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"
                    class="size-3.5 text-[#4A5568] transition-colors group-hover:text-[#C5A059]">
                    <path fill-rule="evenodd"
                        d="M4.25 5.5a.75.75 0 0 1 .75-.75h8.5a.75.75 0 0 1 .75.75v8.5a.75.75 0 0 1-1.5 0V6.56L5.28 14.03a.75.75 0 0 1-1.06-1.06L11.69 5.5H5a.75.75 0 0 1-.75-.75Z"
                        clip-rule="evenodd" />
                </svg>
            </a>
        </div>
    </div>
    @endif

</div>
