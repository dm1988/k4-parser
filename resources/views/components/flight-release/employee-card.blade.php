@props([
    'member',
    'showEmployeeNumber' => true,
])

<li
    data-employee-card
    {{ $attributes->merge(['class' => 'flex min-w-0 items-center gap-3 rounded-lg border border-[#1B365D]/10 bg-white p-3 dark:border-slate-700 dark:bg-slate-900']) }}
>
    <div
        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg text-xs font-black tracking-tighter text-white ring-1 ring-black/5 {{ $member['roleBadgeColor'] }}"
        role="img"
        aria-label="{{ $member['role'] ? 'Crew role '.$member['role'] : 'Crew role not confirmed' }}"
    >
        {{ $member['roleBadgeLabel'] }}
    </div>

    <div class="flex min-w-0 flex-1 flex-col leading-tight">
        <span class="break-words text-base font-extrabold text-[#0B0E14] dark:text-slate-100">{{ $member['name'] }}</span>

        @if ($showEmployeeNumber)
            <div class="flex items-baseline gap-1 font-mono text-xs text-[#64748b] dark:text-slate-400">
                <span class="sr-only">Employee number:</span>
                @if ($member['employeeNumber'])
                    <span aria-hidden="true" class="text-[10px] opacity-60">#</span>
                    <span>{{ $member['employeeNumber'] }}</span>
                @else
                    <span>Not confirmed</span>
                @endif
            </div>
        @endif

        @if ($member['details'])
            <span class="font-mono text-xs text-[#4A5568] dark:text-slate-400">{{ $member['details'] }}</span>
        @endif

        @if ($member['highMins'])
            <span class="mt-1 inline-flex w-fit items-center gap-1 rounded-full bg-amber-100 px-2 py-1 text-[10px] font-bold uppercase tracking-[0.12em] text-amber-900 dark:bg-amber-400/15 dark:text-amber-200">
                <x-heroicon-o-exclamation-triangle class="h-3.5 w-3.5" />
                High mins
            </span>
        @endif
    </div>
</li>
