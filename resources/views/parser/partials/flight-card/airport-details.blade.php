<div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
    <div class="space-y-2 rounded-lg border border-[#1B365D]/10 bg-white px-3 py-2">
        <div class="flex items-center justify-between gap-3">
            <span class="text-[11px] font-semibold uppercase tracking-wide text-[#4A5568]">
                Airport
            </span>
            <span class="min-w-0 truncate font-mono text-sm font-semibold text-[#0B0E14] text-right">
                {{ $model->originIata() }}
            </span>
        </div>

        @if ($model->originName())
            <div class="flex items-center justify-between gap-3">
                <span class="text-[11px] font-semibold uppercase tracking-wide text-[#4A5568]">
                    Name
                </span>
                <p class="min-w-0 truncate text-sm font-semibold text-[#0B0E14] text-right">
                    {{ $model->originName() }}
                </p>
            </div>
        @endif

        @if ($model->originIcao())
            <div class="flex items-center justify-between gap-3">
                <span class="text-[11px] font-semibold uppercase tracking-wide text-[#4A5568]">
                    ICAO
                </span>
                <span class="min-w-0 truncate font-mono text-sm text-[#4A5568] text-right">
                    {{ $model->originIcao() }}
                </span>
            </div>
        @endif

        @if ($model->originCity() || $model->originCountryCode())
            <div class="flex items-center justify-between gap-3">
                <span class="text-[11px] font-semibold uppercase tracking-wide text-[#4A5568]">
                    Location
                </span>
                <span class="min-w-0 truncate text-sm text-[#4A5568] text-right">
                    {{ $model->originCity() }}@if ($model->originCity() && $model->originCountryCode()), @endif{{ $model->originCountryCode() }}
                </span>
            </div>
        @endif
    </div>

    <div class="space-y-2 rounded-lg border border-[#1B365D]/10 bg-white px-3 py-2">
        <div class="flex items-center justify-between gap-3">
            <span class="text-[11px] font-semibold uppercase tracking-wide text-[#4A5568]">
                Airport
            </span>
            <span class="min-w-0 truncate font-mono text-sm font-semibold text-[#0B0E14] text-right">
                {{ $model->destinationIata() }}
            </span>
        </div>

        @if ($model->destinationName())
            <div class="flex items-center justify-between gap-3">
                <span class="text-[11px] font-semibold uppercase tracking-wide text-[#4A5568]">
                    Name
                </span>
                <p class="min-w-0 truncate text-sm font-semibold text-[#0B0E14] text-right">
                    {{ $model->destinationName() }}
                </p>
            </div>
        @endif

        @if ($model->destinationIcao())
            <div class="flex items-center justify-between gap-3">
                <span class="text-[11px] font-semibold uppercase tracking-wide text-[#4A5568]">
                    ICAO
                </span>
                <span class="min-w-0 truncate font-mono text-sm text-[#4A5568] text-right">
                    {{ $model->destinationIcao() }}
                </span>
            </div>
        @endif

        @if ($model->destinationCity() || $model->destinationCountryCode())
            <div class="flex items-center justify-between gap-3">
                <span class="text-[11px] font-semibold uppercase tracking-wide text-[#4A5568]">
                    Location
                </span>
                <span class="min-w-0 truncate text-sm text-[#4A5568] text-right">
                    {{ $model->destinationCity() }}@if ($model->destinationCity() && $model->destinationCountryCode()), @endif{{ $model->destinationCountryCode() }}
                </span>
            </div>
        @endif
    </div>
</div>
