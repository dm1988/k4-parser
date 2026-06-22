<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <p class="text-xs font-semibold uppercase tracking-wide text-[#8A97AB]">
            Flight Number
        </p>
        <p class="mt-1 font-mono text-sm font-semibold text-[#111827]">
            {{ $model->flight->flightNumber ?? '—' }}
        </p>
    </div>

    <div>
        <p class="text-xs font-semibold uppercase tracking-wide text-[#8A97AB]">
            Tail Number
        </p>
        <p class="mt-1 font-mono text-sm font-semibold text-[#111827]">
            {{ $model->flight->tailNumber ?? '—' }}
        </p>
    </div>

    <div>
        <p class="text-xs font-semibold uppercase tracking-wide text-[#8A97AB]">
            Departure
        </p>
        <p class="mt-1 text-sm font-semibold text-[#111827]">
            {{ $model->originTimeLabel() }}
        </p>
    </div>

    <div>
        <p class="text-xs font-semibold uppercase tracking-wide text-[#8A97AB]">
            Arrival
        </p>
        <p class="mt-1 text-sm font-semibold text-[#111827]">
            {{ $model->destinationTimeLabel() }}
        </p>
    </div>

    <div>
        <p class="text-xs font-semibold uppercase tracking-wide text-[#8A97AB]">
            Duration
        </p>
        <p class="mt-1 text-sm font-semibold text-[#111827]">
            {{ $model->flight->durationLabel ?? '—' }}
        </p>
    </div>

    <div>
        <p class="text-xs font-semibold uppercase tracking-wide text-[#8A97AB]">
            Type
        </p>
        <p class="mt-1 text-sm font-semibold text-[#111827]">
            {{ $model->flight->typeLabel ?? '—' }}
        </p>
    </div>
</div>