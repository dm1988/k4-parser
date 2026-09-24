@props([
    'member',
    'showEmployeeNumber' => true,
])

<li
    data-employee-card
    {{ $attributes->merge(['class' => 'flex min-w-0 flex-col gap-2 rounded-lg border border-[#1B365D]/10 bg-white p-3 dark:border-slate-700 dark:bg-slate-900']) }}
>
    <span class="block break-words text-sm font-bold text-[#0B0E14] dark:text-slate-100">{{ $member['name'] }}</span>

    @if ($member['details'])
        <span class="block font-mono text-xs text-[#4A5568] dark:text-slate-400">{{ $member['details'] }}</span>
    @endif

    @if ($member['highMins'])
        <span class="inline-flex w-fit items-center gap-1 rounded-full bg-amber-100 px-2 py-1 text-[10px] font-bold uppercase tracking-[0.12em] text-amber-900 dark:bg-amber-400/15 dark:text-amber-200">
            <x-heroicon-o-exclamation-triangle class="h-3.5 w-3.5" />
            High mins
        </span>
    @endif

    @if ($showEmployeeNumber)
        <div class="flex items-center gap-2 border-t border-[#1B365D]/10 pt-2 dark:border-slate-700">
            <span class="text-[10px] font-bold uppercase tracking-[0.14em] text-[#4A5568] dark:text-slate-400">Employee number</span>
            @if ($member['employeeNumber'])
                <span class="font-mono text-xs font-bold text-[#0B0E14] dark:text-slate-100">{{ $member['employeeNumber'] }}</span>
            @else
                <span class="font-mono text-xs font-semibold text-[#4A5568] dark:text-slate-400">Not confirmed</span>
            @endif
        </div>
    @endif
</li>
