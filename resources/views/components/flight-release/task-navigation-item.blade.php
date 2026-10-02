@props([
    'task',
    'isActive',
    'model',
    'mobile' => false,
])

@php
    $taskCounter = $model->taskCounter($task);
    $wireKeyPrefix = $mobile ? 'flight-plan-task-nav-mobile-' : 'flight-plan-task-nav-';
@endphp

<button
    type="button"
    wire:key="{{ $wireKeyPrefix }}{{ $task->value }}"
    wire:click="selectTask('{{ $task->value }}')"
    wire:loading.attr="disabled"
    wire:target="selectTask('{{ $task->value }}')"
    aria-current="{{ $isActive ? 'page' : 'false' }}"
    aria-controls="flight-plan-task-panel"
    @if ($mobile)
        data-flight-plan-task-option
        @if ($isActive)
            data-flight-plan-active-task
        @endif
        x-on:click="selectTask"
    @endif
    @class([
        'group flex w-full items-center gap-2 rounded-lg border-s-4 px-3 py-2.5 text-left text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#C5A059] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900',
        'border-[#C5A059] bg-[#1B365D] text-white shadow-sm' => $isActive && $mobile,
        'border-transparent text-[#1B365D] hover:bg-[#1B365D]/7 dark:text-slate-200 dark:hover:bg-slate-800' => ! $isActive,
        'border-transparent bg-[#1B365D] text-white shadow-sm' => $isActive && ! $mobile,
    ])
>
    <x-dynamic-component
        :component="'heroicon-o-'.$task->icon()"
        @class([
            'h-4 w-4 shrink-0',
            'text-[#C5A059]' => $isActive,
            'text-[#4A5568] dark:text-slate-400' => ! $isActive,
        ])
    />

    <span class="min-w-0 flex-1 break-words">{{ $task->label() }}</span>

    <span class="ms-auto flex shrink-0 items-center justify-end gap-1.5">
        @if ($task === \App\Enums\FlightPlanTask::FuelScore)
            <x-flight-release.b44-badge
                :label="$model->b44BadgeLabel()"
                wire:key="{{ $wireKeyPrefix }}fuel_score-b44"
            />
        @endif

        @if ($taskCounter !== null)
            <x-flight-release.counter-badge
                :count="$taskCounter"
                :label="$task->label()"
                :noun="match ($task) {
                    \App\Enums\FlightPlanTask::SlotTimes => 'approved slot',
                    \App\Enums\FlightPlanTask::Etops => 'equal-time point',
                    \App\Enums\FlightPlanTask::WeightAndBalance => 'operational weight alert',
                    \App\Enums\FlightPlanTask::Notes => 'note',
                    default => 'item',
                }"
                :tone="$task === \App\Enums\FlightPlanTask::ReviewMelCdl && $taskCounter === 0 ? 'success' : 'warning'"
                :color-classes="$model->taskCounterColorClasses($task)"
            />
        @endif

        @unless ($task === \App\Enums\FlightPlanTask::ReviewMelCdl)
            <x-flight-release.status
                :availability="$model->availabilityFor($task)"
                :absence-is-good="$task->absenceIsGood()"
                dot
            />
        @endunless

        @if ($mobile && $isActive)
            <span class="sr-only">Current task</span>
            <x-heroicon-o-check class="h-4 w-4 shrink-0 text-[#C5A059]" aria-hidden="true" />
        @endif
    </span>
</button>
