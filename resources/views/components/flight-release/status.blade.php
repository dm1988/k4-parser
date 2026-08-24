@props([
    'availability',
    'compact' => false,
    'dot' => false,
])

@if ($availability !== \App\Enums\FlightPlanTaskAvailability::Available)
    @php
        $presentation = match ($availability) {
        \App\Enums\FlightPlanTaskAvailability::NotPresent => [
            'label' => 'Not present',
            'class' => 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200',
            'dotClass' => 'bg-slate-400 dark:bg-slate-500',
        ],
        \App\Enums\FlightPlanTaskAvailability::NotSupported => [
            'label' => 'Not supported',
            'class' => 'bg-amber-100 text-amber-900 dark:bg-amber-400/15 dark:text-amber-200',
            'dotClass' => 'bg-amber-400 dark:bg-amber-300',
        ],
        };
    @endphp

    <span
        @if ($dot)
            role="img"
            aria-label="{{ $presentation['label'] }}"
        @endif
        {{ $attributes->class([
            'inline-flex shrink-0 items-center rounded-full',
            'h-2.5 w-2.5 ring-1 ring-inset ring-black/10 dark:ring-white/10' => $dot,
            'font-bold uppercase tracking-[0.12em]' => ! $dot,
            'px-2 py-1 text-[9px]' => ! $dot && $compact,
            'px-2.5 py-1 text-[10px]' => ! $dot && ! $compact,
            $presentation['dotClass'] => $dot,
            $presentation['class'] => ! $dot,
        ]) }}
    >
        @unless ($dot)
            {{ $presentation['label'] }}
        @endunless
    </span>
@endif
