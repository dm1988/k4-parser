@php
    $crew = $model->crewMembers();
@endphp

<div class="space-y-2">
    <div class="grid gap-2 sm:grid-cols-3">
        <div class="flex items-center justify-between gap-3 rounded-lg bg-[#F8FAFD] px-3 py-2">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-[#8A97AB]">
                Total Crew
            </p>
            <p class="text-sm font-bold text-[#111827]">
                {{ $model->crewCount() }}
            </p>
        </div>

        <div class="flex items-center justify-between gap-3 rounded-lg bg-[#F8FAFD] px-3 py-2">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-[#8A97AB]">
                Operating
            </p>
            <p class="text-sm font-bold text-[#111827]">
                {{ $model->operatingCrewCount() }}
            </p>
        </div>

        <div class="flex items-center justify-between gap-3 rounded-lg bg-[#F8FAFD] px-3 py-2">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-[#8A97AB]">
                Deadheading
            </p>
            <p class="text-sm font-bold text-[#111827]">
                {{ $model->deadheadingCrewCount() }}
            </p>
        </div>
    </div>

    @if (!empty($crew))
        <div class="space-y-2">
            @foreach ($crew as $crewMember)
                <div class="flex items-center justify-between gap-3 rounded-lg bg-[#F8FAFD] px-3 py-2">
                    <p class="truncate text-sm font-semibold text-[#111827]">
                        {{ data_get($crewMember, 'name', 'Unknown Crew Member') }}
                    </p>

                    <div class="flex flex-wrap items-center justify-end gap-2">
                        @if (data_get($crewMember, 'role'))
                            <span class="text-xs font-medium text-[#8090A9]">
                                {{ data_get($crewMember, 'role') }}
                            </span>
                        @endif

                        @if (data_get($crewMember, 'deadheading'))
                            <span class="rounded-full bg-[#E8EDF5] px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide text-[#5E6B80]">
                                Deadhead
                            </span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <p class="text-sm font-medium text-[#8090A9]">
            Individual crew names were not parsed for this flight.
        </p>
    @endif
</div>
