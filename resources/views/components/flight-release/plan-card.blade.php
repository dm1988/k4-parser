@props([
    'model',
    'departureAirport' => null,
    'destinationAirport' => null,
    'alternateAirport' => null,
])

<section class="overflow-hidden rounded-xl border border-[#1B365D]/10 bg-white shadow-sm">
    <div class="flex flex-wrap items-center gap-2 border-b border-[#1B365D]/8 bg-[#F8F9FA] px-4 py-3">
        <x-heroicon-o-paper-airplane class="h-4 w-4 text-[#1B365D]" />
        <span class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#4A5568]">
            Extracted flight plan
        </span>
        <div class="ml-auto flex items-center gap-3">
            <span class="font-mono text-xs font-semibold text-[#0B0E14]">{{ $model->initialAltitude() }}</span>
            <span class="text-[10px] text-[#4A5568]">·</span>
            <span class="font-mono text-xs font-semibold text-[#0B0E14]">{{ $model->duration() }}</span>
        </div>
    </div>

    <div class="grid divide-y divide-[#1B365D]/6 md:grid-cols-3 md:divide-x md:divide-y-0">
        <x-flight-release.airport-card
            label="Departure"
            :code="$model->departure()"
            copy-target="departure-output"
            copy-label="Departure"
            copy-status="departure-status"
        />

        <x-flight-release.airport-card
            label="Destination"
            :code="$model->destination()"
            copy-target="destination-output"
            copy-label="Destination"
            copy-status="destination-status"
        />

        <x-flight-release.airport-card
            label="Alternate"
            :code="$model->alternateLabel()"
            copy-target="alternate-output"
            copy-label="Alternate"
            copy-status="alternate-status"
            :copyable="$model->alternate() !== null"
            :muted="true"
        />
    </div>

    <details class="group border-t border-[#1B365D]/8">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-2 bg-[#F8F9FA] px-4 py-2 text-left transition-colors hover:bg-[#eef0f3] [&::-webkit-details-marker]:hidden">
            <span class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#4A5568]">Airport details</span>
            <x-heroicon-o-chevron-down class="h-3.5 w-3.5 text-[#4A5568]/60 transition group-open:rotate-180" />
        </summary>

        <div class="grid divide-y divide-[#1B365D]/6 border-t border-[#1B365D]/8 md:grid-cols-3 md:divide-x md:divide-y-0">
            <x-flight-release.airport-detail-column
                label="Departure"
                :airport="$departureAirport"
                fallback="Airport details unavailable."
            />

            <x-flight-release.airport-detail-column
                label="Destination"
                :airport="$destinationAirport"
                fallback="Airport details unavailable."
            />

            <x-flight-release.airport-detail-column
                label="Alternate"
                :airport="$alternateAirport"
                :fallback="$model->alternateAirportFallback()"
                :muted="true"
            />
        </div>
    </details>

    <div class="border-t border-[#1B365D]/8">
        <div class="flex items-center justify-between gap-3 bg-[#F8F9FA] px-4 py-2">
            <span class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#4A5568]">Route</span>
            <x-flight-release.copy-button
                target="flight-route-output"
                label="Route"
                status="route-status"
                text="Copy route"
            />
        </div>

        <div class="px-4 py-3">
            <p class="break-words font-mono text-xs leading-relaxed text-[#0B0E14]">
                @foreach ($model->routeTokens() as $token)
                    <x-flight-release.route-token
                        :value="$token['value']"
                        :type="$token['type']"
                        :class="$token['class']"
                    />
                    @if (! $loop->last)
                        <span class="text-[#0B0E14]"> </span>
                    @endif
                @endforeach
            </p>

            <textarea
                id="flight-route-output"
                readonly
                rows="4"
                class="sr-only"
            >{{ $model->route() }}</textarea>

            <p
                id="route-status"
                role="status"
                aria-live="polite"
                class="mt-2 min-h-4 text-[11px] text-[#4A5568] transition-opacity duration-[3000ms]"
            ></p>
        </div>
    </div>
</section>
