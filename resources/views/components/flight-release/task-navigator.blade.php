@props([
    'tasks',
    'activeTask',
    'model',
])

<div {{ $attributes->merge(['class' => 'min-w-0']) }}>
    <nav
        class="hidden h-full min-w-0 border-r border-[#1B365D]/10 bg-white dark:border-slate-700 dark:bg-slate-900 lg:block"
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

        <div class="flex flex-col gap-1 p-3">
            @foreach ($tasks as $task)
                <x-flight-release.task-navigation-item
                    :task="$task"
                    :is-active="$task === $activeTask"
                    :model="$model"
                />
            @endforeach
        </div>
    </nav>

    <nav
        x-data="flightPlanTaskMenu"
        class="min-w-0 lg:hidden"
        aria-label="Flight plan tasks"
    >
        <div class="flex items-center gap-3 border-b border-[#1B365D]/10 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-900">
            <div class="min-w-0 flex-1">
                <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#C5A059]">Active Task</p>
                <p class="truncate text-base font-bold text-[#1B365D] dark:text-slate-100">{{ $activeTask->label() }}</p>
            </div>

            <button
                x-ref="trigger"
                type="button"
                class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-[#1B365D]/15 text-[#1B365D] transition hover:bg-[#1B365D]/7 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#C5A059] focus-visible:ring-offset-2 dark:border-slate-700 dark:text-slate-100 dark:hover:bg-slate-800 dark:focus-visible:ring-offset-slate-900"
                aria-label="Open task menu"
                aria-controls="flight-plan-mobile-task-menu"
                aria-expanded="false"
                x-bind:aria-expanded="open.toString()"
                x-on:click="openMenu"
            >
                <x-heroicon-o-bars-3 class="h-6 w-6" aria-hidden="true" />
            </button>
        </div>

        <div
            x-cloak
            x-show="open"
            id="flight-plan-mobile-task-menu"
            x-ref="dialog"
            role="dialog"
            aria-modal="true"
            aria-labelledby="flight-plan-mobile-task-menu-heading"
            class="fixed inset-0 z-[60] flex h-screen flex-col bg-white shadow-2xl dark:bg-slate-950"
            x-on:keydown.escape.window="dismissMenu"
            x-on:keydown.tab.prevent="trapFocus($event)"
            x-transition:enter="transform transition-transform duration-300 ease-out motion-reduce:transition-none"
            x-transition:enter-start="-translate-y-full motion-reduce:translate-y-0"
            x-transition:enter-end="translate-y-0"
            x-transition:leave="transform transition-transform duration-200 ease-in motion-reduce:transition-none"
            x-transition:leave-start="translate-y-0"
            x-transition:leave-end="-translate-y-full motion-reduce:translate-y-0"
        >
            <div class="flex items-center gap-3 border-b border-[#1B365D]/10 bg-[#F8F9FA] px-4 py-3 dark:border-slate-700 dark:bg-slate-900">
                <div class="min-w-0 flex-1">
                    <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#C5A059]">Active Task</p>
                    <h2
                        id="flight-plan-mobile-task-menu-heading"
                        class="truncate text-base font-bold text-[#1B365D] dark:text-slate-100"
                    >
                        {{ $activeTask->label() }}
                    </h2>
                </div>

                <button
                    type="button"
                    class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-[#1B365D]/15 text-[#1B365D] transition hover:bg-[#1B365D]/7 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#C5A059] focus-visible:ring-offset-2 dark:border-slate-700 dark:text-slate-100 dark:hover:bg-slate-800 dark:focus-visible:ring-offset-slate-900"
                    aria-label="Close task menu"
                    x-on:click="dismissMenu"
                >
                    <x-heroicon-o-x-mark class="h-6 w-6" aria-hidden="true" />
                </button>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto p-3 sm:p-4">
                <div class="flex flex-col gap-1" aria-label="Choose a task">
                    @foreach ($tasks as $task)
                        <x-flight-release.task-navigation-item
                            :task="$task"
                            :is-active="$task === $activeTask"
                            :model="$model"
                            mobile
                        />
                    @endforeach
                </div>
            </div>
        </div>
    </nav>
</div>
