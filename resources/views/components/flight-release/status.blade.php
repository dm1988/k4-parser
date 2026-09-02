@props([
    'availability',
    'label' => null,
    'compact' => false,
    'dot' => false,
    'showAvailable' => false,
    'absenceIsGood' => false,
    'tone' => null,
])

@if ($availability !== \App\Enums\FlightPlanTaskAvailability::Available || $showAvailable || $absenceIsGood)
    <span
        @if ($dot)
            role="img"
            aria-label="{{ $label ?? $availability->label() }}"
        @endif
        {{ $attributes->class([
            'inline-flex shrink-0 items-center rounded-full',
            'h-2.5 w-2.5 ring-1 ring-inset ring-black/10 dark:ring-white/10' => $dot,
            'font-bold uppercase tracking-[0.12em]' => ! $dot,
            'px-2 py-1 text-[9px]' => ! $dot && $compact,
            'px-2.5 py-1 text-[10px]' => ! $dot && ! $compact,
            ($tone ?? $availability->tone($absenceIsGood))->dotColor() => $dot,
            ($tone ?? $availability->tone($absenceIsGood))->badgeColor() => ! $dot,
        ]) }}
    >
        @unless ($dot)
            {{ $label ?? $availability->label() }}
        @endunless
    </span>
@endif
