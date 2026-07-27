<div class="grid gap-2 sm:grid-cols-2">
    <div>
        <x-extract.detail-card label="Flight Number" value-class="whitespace-nowrap font-mono">
            {{ $model->flight->flightNumber ?? '—' }}
        </x-extract.detail-card>
    </div>
    <div>
        <x-extract.detail-card label="Type" value-class="truncate">
            {{ $model->flight->typeLabel ?? '—' }}
        </x-extract.detail-card>
    </div>

    <div class="grid grid-cols-2 gap-2 sm:col-span-2">
        <x-extract.detail-card label="Departure" value-class="whitespace-nowrap">
            {{ $model->originTimeLabel() }}
        </x-extract.detail-card>

        <x-extract.detail-card label="Arrival" value-class="whitespace-nowrap">
            {{ $model->destinationTimeLabel() }}
        </x-extract.detail-card>
    </div>

    @if ($model->hasLegLocalTimes() || $model->hasDutyLocalTimes())
        <section
            data-local-times-group
            class="space-y-2 rounded-xl border border-[#C5A059]/40 bg-[#F8F9FA] p-3 sm:col-span-2"
            aria-labelledby="local-times-heading"
        >
            <h4 id="local-times-heading" class="text-xs font-bold uppercase tracking-wider text-[#1B365D]">
                Local Times
            </h4>

            <div data-local-times-grid class="grid gap-2 sm:grid-cols-2">
                @if ($model->hasLegLocalTimes())
                    <x-extract.detail-card label="Flight Times" value-class="whitespace-nowrap">
                        {{ $model->legLocalTimesLabel() }}
                    </x-extract.detail-card>
                @endif

                @if ($model->hasDutyLocalTimes())
                    <x-extract.detail-card label="Duty Times" value-class="whitespace-nowrap">
                        {{ $model->dutyLocalTimesLabel() }}
                    </x-extract.detail-card>
                @endif
            </div>
        </section>
    @endif

    <div>
        <x-extract.detail-card label="Duration" value-class="truncate">
            {{ $model->flight->durationLabel ?? '—' }}
        </x-extract.detail-card>
    </div>
    <div>
        <x-extract.detail-card label="Aircraft Type" value-class="truncate font-mono">
            {{ $model->flight->aircraft ?? '—' }}
        </x-extract.detail-card>
    </div>
    <div>
        <x-extract.detail-card label="Tail Number" value-class="truncate font-mono">
            {{ $model->flight->tailNumber ?? '—' }}
        </x-extract.detail-card>
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
