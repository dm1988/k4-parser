@props([
    'availability',
    'compact' => false,
])

@php
    $presentation = match ($availability) {
        \App\Enums\FlightPlanTaskAvailability::Available => [
            'label' => 'Available',
            'class' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-400/15 dark:text-emerald-300',
        ],
        \App\Enums\FlightPlanTaskAvailability::NotPresent => [
            'label' => 'Not present',
            'class' => 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200',
        ],
        \App\Enums\FlightPlanTaskAvailability::NotSupported => [
            'label' => 'Not supported',
            'class' => 'bg-amber-100 text-amber-900 dark:bg-amber-400/15 dark:text-amber-200',
        ],
    };
@endphp

<span
    {{ $attributes->class([
        'inline-flex shrink-0 items-center rounded-full font-bold uppercase tracking-[0.12em]',
        'px-2 py-1 text-[9px]' => $compact,
        'px-2.5 py-1 text-[10px]' => ! $compact,
        $presentation['class'],
    ]) }}
>
    {{ $presentation['label'] }}
</span>
