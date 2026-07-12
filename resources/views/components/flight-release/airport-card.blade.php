@props([
    'label',
    'code',
    'copyTarget',
    'copyLabel',
    'copyStatus',
    'airport' => null,
    'fallback' => null,
    'copyable' => true,
])

<div class="rounded-md border border-[#1B365D]/10 bg-white p-4">
    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0 flex-1">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-[#4A5568]">{{ $label }}</p>
            <p id="{{ $copyTarget }}" class="mt-2 font-mono text-lg text-[#0B0E14]">{{ $code }}</p>
        </div>

        @if ($copyable)
            <button
                type="button"
                data-copy-target="{{ $copyTarget }}"
                data-copy-label="{{ $copyLabel }}"
                data-copy-status="{{ $copyStatus }}"
                class="inline-flex h-10 w-10 items-center justify-center rounded-md bg-[#1B365D] text-[#F8F9FA] transition hover:bg-[#142a49]"
            >
                <x-heroicon-o-document-duplicate class="h-5 w-5" />
                <span class="sr-only">Copy {{ strtolower($copyLabel) }}</span>
            </button>
        @endif
    </div>

    @if ($airport || $fallback)
        <details class="group mt-3">
            <summary class="inline-flex h-10 cursor-pointer list-none items-center justify-center gap-2 rounded-md border border-[#1B365D]/15 bg-[#F8F9FA] px-3 text-sm font-semibold text-[#1B365D] transition hover:bg-[#e9edf3] [&::-webkit-details-marker]:hidden">
                <x-heroicon-o-information-circle class="h-5 w-5" />
                <x-heroicon-o-chevron-down class="h-4 w-4 transition group-open:rotate-180" />
            </summary>

            <div class="mt-3 rounded-md border border-[#1B365D]/10 bg-[#F8F9FA] p-3">
                @if ($airport)
                    <p class="text-sm font-medium text-[#1B365D]">{{ $airport['identifiers'] }}</p>
                    <p class="mt-2 text-sm font-medium text-[#1B365D]">{{ $airport['name'] }}</p>
                    <p class="mt-1 text-sm text-[#4A5568]">{{ $airport['location'] }}</p>
                @else
                    <p class="text-sm text-[#4A5568]">{{ $fallback }}</p>
                @endif
            </div>
        </details>
    @endif

    <p
        id="{{ $copyStatus }}"
        role="status"
        aria-live="polite"
        class="mt-2 min-h-5 text-sm text-[#4A5568] transition-opacity duration-[3000ms]"
    ></p>
</div>
