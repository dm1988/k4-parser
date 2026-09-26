@props([
    'task',
    'title',
    'icon',
    'availability',
    'showAction' => true,
    'showStatus' => true,
    'actionLabel' => null,
    'fullCardAction' => true,
    'surfaceClasses' => null,
])

<article
    wire:key="flight-plan-overview-card-{{ $task->value }}"
    {{ $attributes->class([
        'relative flex min-w-0 flex-col gap-4 rounded-xl border p-4 text-left shadow-sm',
        'border-[#1B365D]/10 bg-white dark:border-slate-700 dark:bg-slate-900' => blank($surfaceClasses),
        $surfaceClasses => filled($surfaceClasses),
        'group transition hover:-translate-y-0.5 hover:shadow-md' => $showAction,
        'hover:border-[#C5A059]/70' => $showAction && blank($surfaceClasses),
        'cursor-pointer' => $showAction && $fullCardAction,
    ]) }}
>
    <div class="flex w-full items-start justify-between gap-3">
        <span class="flex min-w-0 items-center gap-3">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[#1B365D] text-[#C5A059] dark:bg-slate-950">
                <x-dynamic-component :component="'heroicon-o-'.$icon" class="h-5 w-5" />
            </span>
            <span class="min-w-0 text-sm font-bold text-[#1B365D] dark:text-slate-100">{{ $title }}</span>
        </span>
        <span class="flex shrink-0 items-center gap-2">
            @isset($badge)
                {{ $badge }}
            @endisset
            @if ($showStatus)
                <x-flight-release.status :availability="$availability" dot />
            @endif
        </span>
    </div>

    <div class="w-full flex-1">
        {{ $slot }}
    </div>

    @if ($showAction)
        @if ($fullCardAction)
            <div aria-hidden="true" class="flex w-full items-center justify-between gap-3 border-t border-[#1B365D]/10 pt-3 text-xs font-bold text-[#1B365D] dark:border-slate-700 dark:text-[#C5A059]">
                {{ $actionLabel ?? $task->actionLabel() }}
                <x-heroicon-o-arrow-right class="h-4 w-4 shrink-0 transition group-hover:translate-x-0.5" />
            </div>

            <button
                type="button"
                wire:click="selectTask('{{ $task->value }}')"
                wire:loading.attr="disabled"
                wire:target="selectTask('{{ $task->value }}')"
                aria-label="{{ $actionLabel ?? $task->actionLabel() }}"
                class="absolute inset-0 z-10 cursor-pointer rounded-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#C5A059] disabled:cursor-wait disabled:bg-white/40 dark:disabled:bg-slate-950/40"
            ></button>
        @else
            <button
                type="button"
                wire:click="selectTask('{{ $task->value }}')"
                wire:loading.attr="disabled"
                wire:target="selectTask('{{ $task->value }}')"
                aria-label="{{ $actionLabel ?? $task->actionLabel() }}"
                class="flex w-full items-center justify-between gap-3 border-t border-[#1B365D]/10 pt-3 text-xs font-bold text-[#1B365D] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#C5A059] disabled:cursor-wait disabled:opacity-70 dark:border-slate-700 dark:text-[#C5A059]"
            >
                {{ $actionLabel ?? $task->actionLabel() }}
                <x-heroicon-o-arrow-right class="h-4 w-4 shrink-0 transition group-hover:translate-x-0.5" />
            </button>
        @endif
    @endif
</article>
