@php
    $crew = $model->crewMembers();
@endphp

<div class="space-y-2">
    <div class="grid gap-2 sm:grid-cols-3">
        <div class="flex items-center justify-between gap-3 rounded-lg border border-[#1B365D]/10 bg-white px-3 py-2">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-[#4A5568]">
                Total Crew
            </p>
            <p class="text-sm font-bold text-[#0B0E14]">
                {{ $model->crewCount() }}
            </p>
        </div>

        <div class="flex items-center justify-between gap-3 rounded-lg border border-[#1B365D]/10 bg-white px-3 py-2">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-[#4A5568]">
                Operating
            </p>
            <p class="text-sm font-bold text-[#0B0E14]">
                {{ $model->operatingCrewCount() }}
            </p>
        </div>

        <div class="flex items-center justify-between gap-3 rounded-lg border border-[#1B365D]/10 bg-white px-3 py-2">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-[#4A5568]">
                Deadheading
            </p>
            <p class="text-sm font-bold text-[#0B0E14]">
                {{ $model->deadheadingCrewCount() }}
            </p>
        </div>
    </div>

    @if (!empty($crew))
        <div class="space-y-2">
            @foreach ($crew as $crewMember)
                <div class="flex items-center justify-between gap-3 rounded-lg border border-[#1B365D]/10 bg-white px-3 py-2">
                    <p class="truncate text-sm font-semibold text-[#0B0E14]">
                        {{ data_get($crewMember, 'name', 'Unknown Crew Member') }}
                    </p>

                    <div class="flex flex-wrap items-center justify-end gap-2">
                        @if (data_get($crewMember, 'role'))
                            <span class="text-xs font-medium text-[#4A5568]">
                                {{ data_get($crewMember, 'role') }}
                            </span>
                        @endif

                        @if (data_get($crewMember, 'deadheading'))
                            <span class="rounded-full bg-[#C5A059]/15 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide text-[#1B365D]">
                                Deadhead
                            </span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <p class="text-sm font-medium text-[#4A5568]">
            Individual crew names were not extracted for this flight.
        </p>
    @endif
</div>
