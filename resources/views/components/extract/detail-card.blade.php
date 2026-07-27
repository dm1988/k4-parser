@props([
    'label',
    'valueClass' => '',
])

<div
    data-detail-card
    {{ $attributes->class('flex items-center justify-between gap-3 rounded-lg border border-[#1B365D]/10 bg-white px-3 py-2') }}
>
    <p class="text-[11px] font-semibold uppercase tracking-wide text-[#4A5568]">
        {{ $label }}
    </p>

    <p @class([
        'min-w-0 text-right text-sm font-semibold text-[#0B0E14]',
        $valueClass,
    ])>
        {{ $slot }}
    </p>
</div>
