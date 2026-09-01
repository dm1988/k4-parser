@props([
    'tasks',
    'activeTask',
    'model',
])

<nav
    {{ $attributes->merge(['class' => 'min-w-0 border-b border-[#1B365D]/10 bg-white dark:border-slate-700 dark:bg-slate-900 lg:border-b-0 lg:border-r']) }}
    aria-labelledby="flight-plan-task-navigation-heading"
>
    <div class="border-b border-[#1B365D]/10 bg-[#F8F9FA] px-4 py-3 dark:border-slate-700 dark:bg-slate-800">
        <h2
            id="flight-plan-task-navigation-heading"
            class="text-xs font-bold uppercase tracking-[0.18em] text-[#1B365D] dark:text-slate-200"
        >
            Task
        </h2>
    </div>

    <div class="flex gap-1 overflow-x-auto p-2 lg:flex-col lg:overflow-visible lg:p-3">
        @foreach ($tasks as $task)
            @php($isActive = $task === $activeTask)
            @php($taskCounter = $model->taskCounter($task))
            <button
                type="button"
                wire:key="flight-plan-task-nav-{{ $task->value }}"
                wire:click="selectTask('{{ $task->value }}')"
                wire:loading.attr="disabled"
                wire:target="selectTask('{{ $task->value }}')"
                aria-current="{{ $isActive ? 'page' : 'false' }}"
                aria-controls="flight-plan-task-panel"
                @class([
                    'group flex shrink-0 items-center gap-2 rounded-lg px-3 py-2.5 text-left text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#C5A059] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900 lg:w-full',
                    'bg-[#1B365D] text-white shadow-sm' => $isActive,
                    'text-[#1B365D] hover:bg-[#1B365D]/7 dark:text-slate-200 dark:hover:bg-slate-800' => ! $isActive,
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
                <span class="whitespace-nowrap lg:min-w-0 lg:flex-1 lg:whitespace-normal">{{ $task->label() }}</span>
                @if ($task === \App\Enums\FlightPlanTask::FuelScore)
                    <x-flight-release.b44-badge
                        :label="$model->b44BadgeLabel()"
                        wire:key="flight-plan-task-nav-fuel_score-b44"
                    />
                @endif
                @if ($taskCounter !== null)
                    <x-flight-release.counter-badge
                        :count="$taskCounter"
                        :label="$task->label()"
                        :noun="$task === \App\Enums\FlightPlanTask::SlotTimes ? 'approved slot' : 'item'"
                    />
                @endif
                <x-flight-release.status
                    :availability="$model->availabilityFor($task)"
                    :absence-is-good="$task->absenceIsGood()"
                    dot
                />
            </button>
        @endforeach
    </div>
</nav>
