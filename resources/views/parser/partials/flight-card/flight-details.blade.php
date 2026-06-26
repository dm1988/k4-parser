<div class="grid gap-2 sm:grid-cols-2">
    <div>
        <div class="flex items-center justify-between gap-3 rounded-lg bg-[#F8FAFD] px-3 py-2">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-[#8A97AB]">
                Flight Number
            </p>
            <p class="font-mono text-sm font-semibold text-[#111827] text-right whitespace-nowrap">
                {{ $model->flight->flightNumber ?? '—' }}
            </p>
        </div>
    </div>
    <div>
        <div class="flex items-center justify-between gap-3 rounded-lg bg-[#F8FAFD] px-3 py-2">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-[#8A97AB]">
                Type
            </p>
            <p class="min-w-0 truncate text-sm font-semibold text-[#111827] text-right">
                {{ $model->flight->typeLabel ?? '—' }}
            </p>
        </div>
    </div>
    @if ( $model->flight->tailNumber )
    <div>
        <div class="flex items-center justify-between gap-3 rounded-lg bg-[#F8FAFD] px-3 py-2">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-[#8A97AB]">
                Flight Tracking
            </p>
            <a href="https://flightaware.com/live/flight/{{ $model->flight->tailNumber }}" target="_blank"
                rel="noopener noreferrer"
                class="inline-flex min-w-0 items-center gap-1 font-mono text-sm font-semibold text-[#111827] hover:text-blue-600 group transition-colors">
                <span class="underline decoration-transparent group-hover:decoration-blue-600 transition-all">Flight
                    Aware</span>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"
                    class="size-3.5 text-gray-400 group-hover:text-blue-600 transition-colors">
                    <path fill-rule="evenodd"
                        d="M4.25 5.5a.75.75 0 0 1 .75-.75h8.5a.75.75 0 0 1 .75.75v8.5a.75.75 0 0 1-1.5 0V6.56L5.28 14.03a.75.75 0 0 1-1.06-1.06L11.69 5.5H5a.75.75 0 0 1-.75-.75Z"
                        clip-rule="evenodd" />
                </svg>
            </a>
        </div>
    </div>
    @endif
    <div class="grid grid-cols-2 gap-2 sm:col-span-2">
        <div class="flex items-center justify-between gap-3 rounded-lg bg-[#F8FAFD] px-3 py-2">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-[#8A97AB]">
                Departure
            </p>
            <p class="text-sm font-semibold text-[#111827] text-right whitespace-nowrap">
                {{ $model->originTimeLabel() }}
            </p>
        </div>

        <div class="flex items-center justify-between gap-3 rounded-lg bg-[#F8FAFD] px-3 py-2">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-[#8A97AB]">
                Arrival
            </p>
            <p class="text-sm font-semibold text-[#111827] text-right whitespace-nowrap">
                {{ $model->destinationTimeLabel() }}
            </p>
        </div>
    </div>

    <div>
        <div class="flex items-center justify-between gap-3 rounded-lg bg-[#F8FAFD] px-3 py-2">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-[#8A97AB]">
                Duration
            </p>
            <p class="min-w-0 truncate text-sm font-semibold text-[#111827] text-right">
                {{ $model->flight->durationLabel ?? '—' }}
            </p>
        </div>
    </div>
    <div>
        <div class="flex items-center justify-between gap-3 rounded-lg bg-[#F8FAFD] px-3 py-2">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-[#8A97AB]">
                Aircraft Type
            </p>
            <p class="min-w-0 truncate font-mono text-sm font-semibold text-[#111827] text-right">
                {{ $model->flight->aircraft ?? '—' }}
            </p>
        </div>
    </div>
    <div>
        <div class="flex items-center justify-between gap-3 rounded-lg bg-[#F8FAFD] px-3 py-2">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-[#8A97AB]">
                Tail Number
            </p>
            <p class="min-w-0 truncate font-mono text-sm font-semibold text-[#111827] text-right">
                {{ $model->flight->tailNumber ?? '—' }}
            </p>
        </div>
    </div>


</div>
