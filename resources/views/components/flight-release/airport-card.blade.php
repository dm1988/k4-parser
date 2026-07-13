@props([
    'label',
    'code',
    'copyTarget',
    'copyLabel',
    'copyStatus',
    'copyable' => true,
    'muted' => false,
])

<div @class([
    'flex h-full flex-col px-3 py-3',
    'bg-amber-50/40' => $muted,
])>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0 flex-1">
            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#4A5568]">{{ $label }}</p>
            <p id="{{ $copyTarget }}" class="mt-1 font-mono text-base font-semibold text-[#0B0E14] sm:text-lg">{{ $code }}</p>
        </div>

        @if ($copyable)
            <x-flight-release.copy-button
                :target="$copyTarget"
                :label="$copyLabel"
                :status="$copyStatus"
                compact
            />
        @endif
    </div>

    <p
        id="{{ $copyStatus }}"
        role="status"
        aria-live="polite"
        class="mt-2 min-h-4 text-[11px] text-[#4A5568] transition-opacity duration-[3000ms]"
    ></p>
</div>
