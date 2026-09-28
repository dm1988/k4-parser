@props(['model'])

<div {{ $attributes->merge(['class' => 'flex min-w-0 flex-col gap-5 p-3 sm:p-5']) }}>
    <div class="grid min-w-0 grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-6">
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

            <dl class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                <x-flight-release.metric label="Alternate" :value="$model->alternate()" empty-text="Not present in this release" />
                <x-flight-release.metric label="Initial altitude" :value="$model->overviewInitialAltitude()" empty-text="Not present in this release" />
                <x-flight-release.metric label="Distance" :value="$model->overviewRouteDistance()" empty-text="Not present in this release" />
            </dl>
        </x-flight-release.overview-card>

        <x-flight-release.overview-card
            :task="\App\Enums\FlightPlanTask::ReviewMelCdl"
            title="MEL / CDL restrictions"
            icon="wrench-screwdriver"
            :availability="$model->availabilityFor(\App\Enums\FlightPlanTask::ReviewMelCdl)"
            :show-action="$model->hasOverviewMelCdlItems()"
            :show-status="false"
            :surface-classes="$model->overviewMelCdlCardClasses()"
            action-label="Review MEL / CDL Details"
            class="xl:col-span-3"
        >
            @if ($model->hasOverviewMelCdlItems())
                <div class="flex flex-col gap-3">
                    <x-flight-release.overview-stat
                        :value="$model->overviewMelCdlItemCount()"
                        label="Active MEL/CDL restrictions"
                        :accessible-label="$model->overviewMelCdlItemCountLabel()"
                    />
                    <p class="text-sm font-medium leading-5 text-[#4A5568] dark:text-slate-300">
                        Review the source-listed MEL/CDL items and associated limitations.
                    </p>
                </div>
            @else
                <div class="flex items-center gap-2 rounded-lg bg-slate-50 px-3 py-2.5 text-sm font-medium text-[#4A5568] dark:bg-slate-800 dark:text-slate-300">
                    <x-heroicon-o-check-circle class="h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
                    <span>No active MEL/CDL restrictions</span>
                </div>
            @endif
        </x-flight-release.overview-card>

        @if ($model->hasOverviewWeightBalanceAlerts())
            <x-flight-release.overview-card
                :task="\App\Enums\FlightPlanTask::WeightAndBalance"
                title="Weight & Balance"
                icon="scale"
                :availability="$model->availabilityFor(\App\Enums\FlightPlanTask::WeightAndBalance)"
                :show-status="false"
                :surface-classes="$model->overviewWeightBalanceAlertCardClasses()"
                class="xl:col-span-2"
            >
                <x-flight-release.overview-stat
                    :value="$model->overviewWeightBalanceAlertCount()"
                    :label="$model->overviewWeightBalanceAlertSummary()"
                    :accessible-label="$model->overviewWeightBalanceAlertCountLabel()"
                />
            </x-flight-release.overview-card>
        @endif

        @if ($model->hasSlotTimes())
            <x-flight-release.overview-card
                :task="\App\Enums\FlightPlanTask::SlotTimes"
                title="Slot times"
                icon="clock"
                :availability="$model->availabilityFor(\App\Enums\FlightPlanTask::SlotTimes)"
                :show-status="false"
                :surface-classes="$model->overviewSlotCardClasses()"
                class="xl:col-span-2"
            >
                <div class="flex flex-col gap-3">
                    <x-flight-release.overview-stat
                        :value="$model->overviewSlotCount()"
                        :label="$model->overviewSlotLabel()"
                        :accessible-label="$model->overviewSlotSummary()"
                    />
                    @foreach ($model->overviewSlotAlerts() as $alert)
                        <p class="flex items-start gap-2 text-sm font-medium text-amber-800 dark:text-amber-200">
                            <x-heroicon-o-exclamation-triangle class="h-4 w-4 shrink-0" aria-hidden="true" />
                            <span>{{ $alert }}</span>
                        </p>
                    @endforeach
                </div>
            </x-flight-release.overview-card>
        @endif

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

        <x-flight-release.overview-card
            :task="\App\Enums\FlightPlanTask::Overview"
            title="GENDEC"
            icon="document-text"
            :availability="$model->availabilityFor(\App\Enums\FlightPlanTask::Overview)"
            :show-action="false"
            :show-status="false"
            :surface-classes="$model->overviewGendecCardClasses()"
            id="overview-gendec-card"
            class="xl:col-span-2"
        >
            <p @class([
                'flex items-start gap-2 text-sm font-medium leading-5',
                'text-amber-900 dark:text-amber-200' => $model->overviewGendecNeedsReview(),
                'text-[#4A5568] dark:text-slate-300' => ! $model->overviewGendecNeedsReview(),
            ])>
                @if ($model->overviewGendecNeedsReview())
                    <x-heroicon-o-exclamation-triangle class="h-4 w-4 shrink-0" aria-hidden="true" />
                @elseif ($model->hasGeneralDeclaration())
                    <x-heroicon-o-check-circle class="h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
                @endif
                <span>{{ $model->overviewGendecMessage() }}</span>
            </p>
        </x-flight-release.overview-card>

        @if ($model->shouldShowEtopsOverviewCard())
            <x-flight-release.overview-card
                :task="\App\Enums\FlightPlanTask::Etops"
                title="ETOPS evidence"
                icon="globe-alt"
                :availability="$model->availabilityFor(\App\Enums\FlightPlanTask::Etops)"
                class="xl:col-span-2"
            >
                <dl class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <x-flight-release.metric label="ETP points" :value="$model->overviewEtpCount()" empty-text="Not present in this release" />
                    <x-flight-release.metric label="ETOPS time" :value="$model->overviewEtopsTime()" empty-text="Not present in this release" />
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

        <div class="grid grid-cols-1 gap-px bg-[#1B365D]/10 dark:bg-slate-700 sm:grid-cols-2 xl:grid-cols-[repeat(auto-fit,minmax(0,1fr))]">
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
