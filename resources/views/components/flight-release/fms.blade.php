@props([
    'model',
    'departureAirport' => null,
    'destinationAirport' => null,
    'alternateAirport' => null,
])

<div {{ $attributes->merge(['class' => 'flex min-w-0 flex-col gap-5 p-3 sm:p-5']) }}>
    <section aria-labelledby="fms-fields-heading" class="flex min-w-0 flex-col gap-3">
        <div>
            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#4A5568] dark:text-slate-400">Confirmed release values</p>
            <h3 id="fms-fields-heading" class="text-base font-bold text-[#1B365D] dark:text-slate-100">FMS route setup</h3>
        </div>

        <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($model->fmsFields() as $field)
                <x-flight-release.metric
                    :label="$field['label']"
                    :value="$field['value']"
                    empty-text="Not present in this release"
                />
            @endforeach
        </dl>
    </section>

    <section aria-labelledby="fms-airports-heading" class="overflow-hidden rounded-xl border border-[#1B365D]/10 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="border-b border-[#1B365D]/10 bg-[#F8F9FA] px-4 py-3 dark:border-slate-700 dark:bg-slate-800">
            <h3 id="fms-airports-heading" class="text-xs font-bold uppercase tracking-[0.16em] text-[#1B365D] dark:text-slate-200">Airport context</h3>
        </div>

        <div class="grid divide-y divide-[#1B365D]/6 dark:divide-slate-700 md:grid-cols-3 md:divide-x md:divide-y-0">
            <x-flight-release.airport-detail-column
                label="Departure"
                :code="$model->departure()"
                :airport="$departureAirport"
                fallback="Airport details unavailable."
            />
            <x-flight-release.airport-detail-column
                label="Destination"
                :code="$model->destination()"
                :airport="$destinationAirport"
                fallback="Airport details unavailable."
            />
            <x-flight-release.airport-detail-column
                label="Alternate"
                :code="$model->alternate()"
                :airport="$alternateAirport"
                :fallback="$model->alternateAirportFallback()"
                :muted="true"
            />
        </div>
    </section>

    <section aria-labelledby="fms-procedures-heading" class="flex min-w-0 flex-col gap-3">
        <h3 id="fms-procedures-heading" class="text-xs font-bold uppercase tracking-[0.16em] text-[#1B365D] dark:text-slate-200">Planned runways and procedures</h3>

        <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <x-flight-release.metric label="Departure runway" :value="$model->departureRunway()" empty-text="Not present in this release" />
            <x-flight-release.metric label="SID" :value="$model->departureSid()" empty-text="Not present in this release" />
            <x-flight-release.metric label="Arrival runway" :value="$model->arrivalRunway()" empty-text="Not present in this release" />
            <x-flight-release.metric label="STAR" :value="$model->arrivalStar()" empty-text="Not present in this release" />
        </dl>
    </section>

    <section aria-labelledby="fms-route-heading" class="overflow-hidden rounded-xl border border-[#1B365D]/10 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="border-b border-[#1B365D]/10 bg-[#F8F9FA] px-4 py-3 dark:border-slate-700 dark:bg-slate-800">
            <h3 id="fms-route-heading" class="text-xs font-bold uppercase tracking-[0.16em] text-[#1B365D] dark:text-slate-200">Route</h3>
        </div>

        <div class="overflow-x-auto px-4 py-3">
            @if ($model->routeTokens() === [])
                <p class="text-sm font-medium text-[#4A5568] dark:text-slate-400">No confirmed route was found in this release.</p>
            @else
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
            @endif
        </div>
    </section>

    <x-flight-release.source-evidence message="FMS values are repeated from normalized release data. Unsupported performance values are not inferred, and raw source evidence remains private." />
</div>
