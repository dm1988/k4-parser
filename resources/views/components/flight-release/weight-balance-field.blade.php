@props(['field'])

@php
    $sourceStatusClasses = match ($field['sourceStatus']) {
        'confirmed' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-400/15 dark:text-emerald-200',
        'conflict' => 'bg-red-100 text-red-800 dark:bg-red-400/15 dark:text-red-200',
        default => 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200',
    };
@endphp

<article class="flex min-w-0 flex-col gap-3 rounded-lg border border-[#1B365D]/10 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
    <div class="flex items-start justify-between gap-3">
        <h3 class="text-xs font-bold uppercase tracking-[0.14em] text-[#1B365D] dark:text-slate-200">
            {{ $field['label'] }}
        </h3>
        @unless ($field['sourceStatus'] === 'confirmed')
            <span class="inline-flex shrink-0 rounded-full px-2 py-1 text-[9px] font-bold uppercase tracking-[0.12em] {{ $sourceStatusClasses }}">
                {{ $field['sourceStatusLabel'] }}
            </span>
        @endunless
    </div>

    <div class="flex-1">
        <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-[#4A5568] dark:text-slate-400">Planned</p>
        <p class="mt-1 flex items-baseline gap-1.5 font-mono text-[#0B0E14] dark:text-slate-100">
            <span class="text-xl font-black tracking-tight">{{ $field['plannedAmount'] ?? $field['sourceStatusLabel'] }}</span>
            @if ($field['plannedUnit'])
                <span class="text-[10px] font-bold tracking-[0.12em] text-[#4A5568] dark:text-slate-400">{{ $field['plannedUnit'] }}</span>
            @endif
        </p>
    </div>

    @if ($field['limitAmount'])
        <div class="border-t border-[#1B365D]/10 pt-3 dark:border-slate-700">
            <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-[#4A5568] dark:text-slate-400">Permitted limit</p>
            <p class="mt-1 flex items-baseline gap-1 font-mono text-sm font-bold text-[#0B0E14] dark:text-slate-100">
                {{ $field['limitAmount'] }}
                <span class="text-[9px] text-[#4A5568] dark:text-slate-400">{{ $field['limitUnit'] }}</span>
            </p>
        </div>
    @endif

    @if ($field['derived'])
        <footer class="border-t border-[#1B365D]/10 pt-3 text-[11px] font-medium leading-4 text-[#4A5568] dark:border-slate-700 dark:text-slate-400">
            Derived server-side from confirmed zero-fuel weight and ramp fuel.
        </footer>
    @endif
</article>
