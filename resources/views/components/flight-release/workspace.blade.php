@props([
    'tasks',
    'activeTask',
    'model',
])

@php($availability = $model->availabilityFor($activeTask))

<div {{ $attributes->merge(['class' => 'flex min-w-0 flex-col gap-4']) }}>
    <x-flight-release.release-header id="release-summary" class="scroll-mt-6" :model="$model" />

    <div class="min-w-0 overflow-hidden rounded-xl border border-[#1B365D]/10 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900 lg:grid lg:grid-cols-[15rem_minmax(0,1fr)]">
        <x-flight-release.task-navigator
            :tasks="$tasks"
            :active-task="$activeTask"
            :model="$model"
        />

        <section
            id="flight-plan-task-panel"
            wire:key="flight-plan-task-panel-{{ $activeTask->value }}"
            class="min-w-0"
            aria-labelledby="flight-plan-task-title-{{ $activeTask->value }}"
        >
            <x-flight-release.section-header
                id="flight-plan-task-title-{{ $activeTask->value }}"
                :title="$activeTask->label()"
                :icon="$activeTask->icon()"
                :availability="$availability"
                :absence-is-good="$activeTask->absenceIsGood()"
            />

            @switch(true)
                @case($availability === \App\Enums\FlightPlanTaskAvailability::NotPresent)
                    <x-flight-release.empty-state
                        title="Not present in this release"
                        :message="$activeTask->label().' data was not found in the confirmed release fields. No value or status has been inferred.'"
                        icon="document-magnifying-glass"
                    />
                    @break

                @case($availability === \App\Enums\FlightPlanTaskAvailability::NotSupported)
                    <x-flight-release.empty-state
                        title="Not supported yet"
                        :message="$activeTask->label().' requires confirmed fixtures and typed extraction before it can be displayed safely.'"
                        icon="wrench-screwdriver"
                    />
                    @break

                @case(! $activeTask->hasCustomView())
                    <div class="flex flex-col gap-4 p-4 sm:p-5">
                        <x-flight-release.empty-state
                            title="Source-backed data available"
                            :message="$activeTask->label().' is available for this release. Its dedicated operational layout is scheduled in the next focused task.'"
                            icon="check-circle"
                            class="min-h-52 rounded-lg bg-[#F8F9FA] dark:bg-slate-800/60"
                        />
                        <x-flight-release.source-evidence />
                    </div>
                    @break

                @case($activeTask === \App\Enums\FlightPlanTask::JeppPdPro)
                    <div class="p-3 sm:p-4">
                        <x-dynamic-component
                            :component="$activeTask->componentName()"
                            :model="$model"
                            :departure-airport="$model->departureAirport()"
                            :destination-airport="$model->destinationAirport()"
                            :alternate-airport="$model->alternateAirport()"
                        />
                    </div>
                    @break

                @case($activeTask->requiresAirports())
                    <x-dynamic-component
                        :component="$activeTask->componentName()"
                        :model="$model"
                        :departure-airport="$model->departureAirport()"
                        :destination-airport="$model->destinationAirport()"
                        :alternate-airport="$model->alternateAirport()"
                    />
                    @break

                @default
                    <x-dynamic-component :component="$activeTask->componentName()" :model="$model" />
            @endswitch
        </section>
    </div>
</div>
