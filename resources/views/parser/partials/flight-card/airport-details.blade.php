<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div>
        <span class="mb-2 block font-mono text-[11px] font-bold uppercase tracking-wider text-[#8A97AB]">
            {{ $model->originIata() }}
        </span>

        <div class="flex flex-col gap-1.5">
            @if ($model->originName())
                <p class="text-sm font-semibold text-[#111827]">
                    {{ $model->originName() }}
                </p>
            @endif

            @if ($model->originIcao())
                <div class="flex items-center gap-1.5 text-sm text-[#5E6B80]">
                    <x-heroicon-o-signal class="h-3.5 w-3.5 shrink-0 text-[#8A97AB]" />
                    <span class="font-mono">ICAO: {{ $model->originIcao() }}</span>
                </div>
            @endif

            @if ($model->originCity() || $model->originCountryCode())
                <div class="flex items-center gap-1.5 text-sm text-[#5E6B80]">
                    <x-heroicon-o-map-pin class="h-3.5 w-3.5 shrink-0 text-[#8A97AB]" />
                    <span>
                        {{ $model->originCity() }}

                        @if ($model->originCity() && $model->originCountryCode())
                            ,
                        @endif

                        {{ $model->originCountryCode() }}
                    </span>
                </div>
            @endif
        </div>
    </div>

    <div class="border-t border-[#E8EDF5] pt-4 sm:border-l sm:border-t-0 sm:pl-4 sm:pt-0">
        <span class="mb-2 block font-mono text-[11px] font-bold uppercase tracking-wider text-[#8A97AB]">
            {{ $model->destinationIata() }}
        </span>

        <div class="flex flex-col gap-1.5">
            @if ($model->destinationName())
                <p class="text-sm font-semibold text-[#111827]">
                    {{ $model->destinationName() }}
                </p>
            @endif

            @if ($model->destinationIcao())
                <div class="flex items-center gap-1.5 text-sm text-[#5E6B80]">
                    <x-heroicon-o-signal class="h-3.5 w-3.5 shrink-0 text-[#8A97AB]" />
                    <span class="font-mono">ICAO: {{ $model->destinationIcao() }}</span>
                </div>
            @endif

            @if ($model->destinationCity() || $model->destinationCountryCode())
                <div class="flex items-center gap-1.5 text-sm text-[#5E6B80]">
                    <x-heroicon-o-map-pin class="h-3.5 w-3.5 shrink-0 text-[#8A97AB]" />
                    <span>
                        {{ $model->destinationCity() }}

                        @if ($model->destinationCity() && $model->destinationCountryCode())
                            ,
                        @endif

                        {{ $model->destinationCountryCode() }}
                    </span>
                </div>
            @endif
        </div>
    </div>
</div>