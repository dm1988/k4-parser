@props(['model'])

<div {{ $attributes->merge(['class' => 'flex min-w-0 flex-col gap-5 p-3 sm:p-5']) }}>
    <div class="grid min-w-0 grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-6">
        <x-flight-release.overview-card
            :task="\App\Enums\FlightPlanTask::FlightInit"
            title="Flight and aircraft"
            icon="bolt"
            :availability="$model->availabilityFor(\App\Enums\FlightPlanTask::FlightInit)"
            class="xl:col-span-3"
        >
            <dl class="grid grid-cols-2 gap-2">
                <x-flight-release.metric label="Flight" :value="$model->flightNumber()" empty-text="Not present in this release" />
                <x-flight-release.metric label="Flight date" :value="$model->flightDate()" empty-text="Not present in this release" />
                <x-flight-release.metric label="Aircraft" :value="$model->aircraftType()" empty-text="Not present in this release" />
                <x-flight-release.metric label="Tail" :value="$model->tailNumber()" empty-text="Not present in this release" />
            </dl>
        </x-flight-release.overview-card>

        <x-flight-release.overview-card
            :task="\App\Enums\FlightPlanTask::Fms"
            title="Route"
            icon="calculator"
            :availability="$model->availabilityFor(\App\Enums\FlightPlanTask::Fms)"
            class="xl:col-span-3"
        >
            <x-slot:badge>
                <x-flight-release.b44-badge :label="$model->b44BadgeLabel()" />
            </x-slot:badge>

            <dl class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                <x-flight-release.metric label="Departure" :value="$model->departure()" empty-text="Not present in this release" />
                <x-flight-release.metric label="Destination" :value="$model->destination()" empty-text="Not present in this release" />
                <x-flight-release.metric label="Alternate" :value="$model->alternate()" empty-text="Not present in this release" />
                <x-flight-release.metric label="Initial altitude" :value="$model->overviewInitialAltitude()" empty-text="Not present in this release" />
                <x-flight-release.metric label="Distance" :value="$model->overviewRouteDistance()" empty-text="Not present in this release" class="sm:col-span-2" />
            </dl>
        </x-flight-release.overview-card>

        <x-flight-release.overview-card
            :task="\App\Enums\FlightPlanTask::SlotTimes"
            title="Schedule and slots"
            icon="clock"
            :availability="$model->availabilityFor(\App\Enums\FlightPlanTask::SlotTimes)"
            :show-action="$model->hasSlotTimes()"
            :show-status="$model->hasSlotTimes()"
            class="xl:col-span-2"
        >
            <dl class="grid grid-cols-1 gap-2 sm:grid-cols-2 md:grid-cols-1">
                <x-flight-release.metric label="ETD (UTC)" :value="$model->overviewEtdUtc()" empty-text="Not present in this release" />
                <x-flight-release.metric label="ETA (UTC)" :value="$model->overviewEtaUtc()" empty-text="Not present in this release" />
                @if ($model->hasSlotTimes())
                    <x-flight-release.metric label="Approved slots" :value="$model->overviewSlotSummary()" empty-text="Not present in this release" />
                @endif
            </dl>
        </x-flight-release.overview-card>

        <x-flight-release.overview-card
            :task="\App\Enums\FlightPlanTask::FuelScore"
            title="Fuel"
            icon="chart-bar-square"
            :availability="$model->availabilityFor(\App\Enums\FlightPlanTask::FuelScore)"
            class="xl:col-span-2"
        >
            <dl>
                <x-flight-release.metric label="Ramp fuel" :value="$model->overviewRampFuel()" empty-text="Not present in this release" />
            </dl>
        </x-flight-release.overview-card>

        @if ($model->shouldShowEtopsOverviewCard())
            <x-flight-release.overview-card
                :task="\App\Enums\FlightPlanTask::Etops"
                title="ETOPS evidence"
                icon="globe-alt"
                :availability="$model->availabilityFor(\App\Enums\FlightPlanTask::Etops)"
                class="xl:col-span-2"
            >
                <dl>
                    <x-flight-release.metric label="Confirmed release fields" :value="$model->overviewEtopsSummary()" empty-text="Not present in this release" />
                </dl>
            </x-flight-release.overview-card>
        @endif
    </div>

    <section aria-labelledby="overview-support-status-heading" class="overflow-hidden rounded-xl border border-[#1B365D]/10 bg-white dark:border-slate-700 dark:bg-slate-900">
        <header class="border-b border-[#1B365D]/10 bg-[#F8F9FA] px-4 py-3 dark:border-slate-700 dark:bg-slate-800">
            <h3 id="overview-support-status-heading" class="text-xs font-bold uppercase tracking-[0.16em] text-[#1B365D] dark:text-slate-200">
                Operational support status
            </h3>
        </header>

        <div class="grid grid-cols-1 gap-px bg-[#1B365D]/10 dark:bg-slate-700 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($model->overviewUnsupportedIndicators() as $indicator)
                <div class="flex items-center justify-between gap-3 bg-white px-4 py-3 dark:bg-slate-900">
                    <span class="text-sm font-semibold text-[#0B0E14] dark:text-slate-100">{{ $indicator['label'] }}</span>
                    <x-flight-release.status
                        :availability="$indicator['availability']"
                        :label="$indicator['statusLabel'] ?? null"
                        :compact="true"
                        :show-available="true"
                        :absence-is-good="$indicator['absenceIsGood'] ?? false"
                        :tone="$indicator['tone'] ?? null"
                    />
                </div>
            @endforeach
        </div>
    </section>

    <details class="group overflow-hidden rounded-xl border border-[#1B365D]/10 bg-white dark:border-slate-700 dark:bg-slate-900">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 bg-[#F8F9FA] px-4 py-3 text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#C5A059] dark:bg-slate-800 [&::-webkit-details-marker]:hidden">
            <span class="flex items-center gap-2 text-xs font-bold uppercase tracking-[0.16em] text-[#1B365D] dark:text-slate-200">
                <x-heroicon-o-building-office-2 class="h-4 w-4" />
                Airport details
            </span>
            <x-heroicon-o-chevron-down class="h-4 w-4 text-[#4A5568] transition group-open:rotate-180 dark:text-slate-400" />
        </summary>

        <div class="grid divide-y divide-[#1B365D]/10 border-t border-[#1B365D]/10 dark:divide-slate-700 dark:border-slate-700 md:grid-cols-3 md:divide-x md:divide-y-0">
            <x-flight-release.airport-detail-column
                label="Departure"
                :airport="$model->departureAirport()"
                fallback="Airport details unavailable."
            />
            <x-flight-release.airport-detail-column
                label="Destination"
                :airport="$model->destinationAirport()"
                fallback="Airport details unavailable."
            />
            <x-flight-release.airport-detail-column
                label="Alternate"
                :airport="$model->alternateAirport()"
                :fallback="$model->alternateAirportFallback()"
                :muted="true"
            />
        </div>
    </details>
</div>
