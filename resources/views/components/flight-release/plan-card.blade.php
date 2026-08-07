@props([
    'model',
    'departureAirport' => null,
    'destinationAirport' => null,
    'alternateAirport' => null,
])

<section class="overflow-hidden rounded-xl border border-[#1B365D]/10 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
    <div class="flex flex-wrap items-center gap-2 border-b border-[#1B365D]/8 bg-[#F8F9FA] px-4 py-3 dark:border-slate-700 dark:bg-slate-800">
        <x-heroicon-o-paper-airplane class="h-4 w-4 text-[#1B365D] dark:text-slate-200" />
        <span class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#4A5568] dark:text-slate-400">
            Extracted flight plan
        </span>
        <div class="ml-auto flex items-center gap-3">
            <span class="font-mono text-xs font-semibold text-[#0B0E14] dark:text-slate-100">{{ $model->initialAltitude() }}</span>
            <span class="text-[10px] text-[#4A5568] dark:text-slate-500">·</span>
            <span class="font-mono text-xs font-semibold text-[#0B0E14] dark:text-slate-100">{{ $model->duration() }}</span>
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

    @if ($model->hasPlannedRunways())
        <div class="grid grid-cols-2 divide-x divide-[#1B365D]/6 border-t border-[#1B365D]/8 bg-white dark:divide-slate-700 dark:border-slate-700 dark:bg-slate-900">
            <div class="flex min-w-0 flex-col gap-2 px-4 py-3">
                <span class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#4A5568] dark:text-slate-400">
                    Departure runway
                </span>
                <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                    <span class="font-mono text-lg font-bold text-[#1B365D] dark:text-slate-100">{{ $model->departureRunway() ?? '—' }}</span>
                    @if ($model->departureSid())
                        <span class="break-words font-mono text-xs font-semibold text-[#0B0E14] dark:text-slate-100">
                            <span class="text-[10px] font-bold uppercase tracking-[0.14em] text-[#4A5568] dark:text-slate-400">SID</span>
                            {{ $model->departureSid() }}
                        </span>
                    @endif
                </div>
            </div>

            <div class="flex min-w-0 flex-col gap-2 px-4 py-3">
                <span class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#4A5568] dark:text-slate-400">
                    Arrival runway
                </span>
                <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                    <span class="font-mono text-lg font-bold text-[#1B365D] dark:text-slate-100">{{ $model->arrivalRunway() ?? '—' }}</span>
                    @if ($model->arrivalStar())
                        <span class="break-words font-mono text-xs font-semibold text-[#0B0E14] dark:text-slate-100">
                            <span class="text-[10px] font-bold uppercase tracking-[0.14em] text-[#4A5568] dark:text-slate-400">STAR</span>
                            {{ $model->arrivalStar() }}
                        </span>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <details class="group border-t border-[#1B365D]/8">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-2 bg-[#F8F9FA] px-4 py-2 text-left transition-colors hover:bg-[#eef0f3] dark:bg-slate-800 dark:hover:bg-slate-700 [&::-webkit-details-marker]:hidden">
            <div class="flex items-center gap-2">
                <x-heroicon-o-information-circle class="h-4 w-4 text-[#1B365D] dark:text-slate-300" />
                <span class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#4A5568] dark:text-slate-400">
                    Airport details
                </span>
            </div>
            <x-heroicon-o-chevron-down class="h-3.5 w-3.5 text-[#4A5568]/60 transition group-open:rotate-180 dark:text-slate-500" />
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

    @if ($model->hasEtopsData())
        <section class="border-t border-[#1B365D]/8">
            <div class="flex items-center gap-2 bg-[#F8F9FA] px-4 py-2 dark:bg-slate-800">
                <x-heroicon-o-globe-alt class="h-4 w-4 text-[#1B365D] dark:text-slate-300" />
                <span class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#4A5568] dark:text-slate-400">
                    ETOPS critical points
                </span>
            </div>

            @if ($model->etps() !== [])
                <div class="grid gap-3 border-t border-[#1B365D]/8 p-4 md:grid-cols-2">
                    @foreach ($model->etps() as $etp)
                        <div class="min-w-0 space-y-3 rounded-lg border border-[#1B365D]/10 bg-white p-3 dark:border-slate-700 dark:bg-slate-800">
                            <div class="grid grid-cols-2 gap-2">
                                @foreach ($model->etpAirports($etp) as $airport)
                                    <x-flight-release.copy-field
                                        :id="'etp-'.$loop->parent->index.'-airport-'.$loop->index"
                                        :label="'Airport '.($loop->index + 1)"
                                        :value="$airport"
                                    />
                                @endforeach
                            </div>

                            <x-flight-release.copy-field
                                :id="'etp-'.$loop->index.'-coordinates'"
                                label="Coordinates"
                                :value="$etp['coordinates']"
                            />

                            <p class="break-words text-[11px] font-semibold text-[#4A5568] dark:text-slate-400">
                                {{ $etp['scenario'] }}
                            </p>
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($model->eentCoordinates() || $model->eexpCoordinates())
                <div class="grid gap-3 border-t border-[#1B365D]/8 p-4 md:grid-cols-2">
                    @if ($model->eentCoordinates())
                        <x-flight-release.copy-field
                            id="eent-coordinates"
                            label="EENT coordinates"
                            :value="$model->eentCoordinates()"
                        />
                    @endif

                    @if ($model->eexpCoordinates())
                        <x-flight-release.copy-field
                            id="eexp-coordinates"
                            label="EEXP coordinates"
                            :value="$model->eexpCoordinates()"
                        />
                    @endif
                </div>
            @endif
        </section>
    @endif

    <div class="border-t border-[#1B365D]/8">
        <div class="flex items-center justify-between gap-3 bg-[#F8F9FA] px-4 py-2 dark:bg-slate-800">
            <div class="flex items-center gap-2">
                <x-heroicon-o-map class="h-4 w-4 text-[#1B365D] dark:text-slate-300" />
                <span class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#4A5568] dark:text-slate-400">
                    Route
                </span>
            </div>
            <x-flight-release.copy-button
                target="flight-route-output"
                label="Route"
                status="route-status"
                text="Copy route"
            />
        </div>

        <div class="px-4 py-3">
            <p class="break-words font-mono text-xs leading-relaxed text-[#0B0E14] dark:text-slate-100">
                @foreach ($model->routeTokens() as $token)
                    <x-flight-release.route-token
                        :value="$token['value']"
                        :type="$token['type']"
                        :class="$token['class']"
                    />
                    @if (! $loop->last)
                        <span class="text-[#0B0E14] dark:text-slate-100"> </span>
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
                class="mt-2 min-h-4 text-[11px] text-[#4A5568] transition-opacity duration-[3000ms] dark:text-slate-400"
            ></p>
        </div>
    </div>
</section>
