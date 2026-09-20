@props([
    'heading',
    'plannedTime',
    'position',
    'comparison',
])

<div {{ $attributes->merge(['class' => 'col-span-2 flex flex-col gap-2 border-t border-[#1B365D]/10 pt-3 dark:border-slate-700']) }}>
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <dt class="text-[10px] font-bold uppercase tracking-[0.14em] text-[#4A5568] dark:text-slate-400">{{ $heading }}</dt>
        <dd class="font-mono text-xs font-semibold tabular-nums text-[#0B0E14] dark:text-slate-100">{{ $plannedTime }}</dd>
    </div>
    <div class="relative h-3 rounded-full bg-[#1B365D]/10 dark:bg-slate-700" aria-hidden="true">
        <div class="absolute inset-y-0 left-1/4 right-1/4 rounded-full bg-[#B8860B]/35 dark:bg-amber-400/30"></div>
        <div @class([
            'absolute -top-1 h-5 w-1 -translate-x-1/2 rounded-full bg-[#1B365D] dark:bg-blue-300',
            'left-0' => $position < 12.5,
            'left-1/4' => $position >= 12.5 && $position < 37.5,
            'left-1/2' => $position >= 37.5 && $position < 62.5,
            'left-3/4' => $position >= 62.5 && $position < 87.5,
            'left-full' => $position >= 87.5,
        ])></div>
    </div>
    <div class="flex justify-between gap-3 text-[10px] font-semibold text-[#4A5568] dark:text-slate-400">
        <span>Earlier</span>
        <span class="text-center text-[#B8860B] dark:text-amber-300">Confirmed window</span>
        <span>Later</span>
    </div>
    <dd class="text-xs font-semibold text-[#1B365D] dark:text-blue-200">{{ $comparison }}</dd>
</div>
