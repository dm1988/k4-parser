@props([
    'info',
    'align' => 'left',
])

<div
    x-data="{ open: false }"
    x-on:keydown.escape.window="open = false"
    @class([
        'relative inline-block',
        'text-right' => $align === 'right',
        'text-left' => $align !== 'right',
    ])
>
    <button
        type="button"
        x-on:click="open = ! open"
        x-bind:aria-expanded="open.toString()"
        aria-label="Airport info for {{ $info['iata'] }}"
        @class([
            'group flex flex-col gap-0.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#C5A059]/50 focus-visible:ring-offset-2 rounded-md',
            'items-end text-right' => $align === 'right',
            'items-start text-left' => $align !== 'right',
        ])
    >
        <span class="font-mono text-xl font-bold tracking-[0.04em] text-[#1B365D] transition-colors group-hover:text-[#C5A059] sm:text-2xl">
            {{ $info['iata'] }}
        </span>

        <span class="inline-flex items-center gap-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-[#4A5568]/70 transition-colors group-hover:text-[#C5A059]">
            <x-heroicon-o-information-circle class="h-3 w-3" />
            info
        </span>
    </button>

    <div
        x-show="open"
        x-on:click.outside="open = false"
        x-transition.opacity.scale.95.origin.top
        style="display: none;"
        @class([
            'absolute top-full z-50 mt-2 w-64 overflow-hidden rounded-xl border border-[#1B365D]/12 bg-white shadow-xl shadow-[#1B365D]/10 ring-1 ring-[#1B365D]/5',
            'right-0' => $align === 'right',
            'left-0' => $align !== 'right',
        ])
    >
        <div class="flex items-center justify-between border-b border-[#1B365D]/10 bg-[#E9F0F8] px-4 py-2.5">
            <span class="font-mono text-[11px] font-bold uppercase tracking-[0.18em] text-[#1B365D]">
                Airport Info
            </span>

            <button
                type="button"
                x-on:click="open = false"
                class="flex h-5 w-5 items-center justify-center rounded-full text-[#4A5568] transition-colors hover:bg-[#1B365D]/10"
                aria-label="Close airport info"
            >
                <x-heroicon-o-x-mark class="h-3.5 w-3.5" />
            </button>
        </div>

        <div class="space-y-3 px-4 py-3">
            <p class="text-[13px] font-semibold leading-snug text-[#0B0E14]">
                {{ $info['name'] }}
            </p>

            @if ($info['location'])
                <p class="text-[12px] text-[#4A5568]">
                    {{ $info['location'] }}
                </p>
            @endif

            <div class="h-px bg-[#1B365D]/8"></div>

            <div class="grid grid-cols-2 gap-2">
                <div class="rounded-lg border border-[#1B365D]/10 bg-[#F8F9FA] px-3 py-2 text-left">
                    <p class="text-[10px] font-semibold uppercase tracking-widest text-[#4A5568]">
                        IATA
                    </p>
                    <p class="mt-0.5 font-mono text-base font-bold text-[#1B365D]">
                        {{ $info['iata'] }}
                    </p>
                </div>

                <div class="rounded-lg border border-[#1B365D]/10 bg-[#F8F9FA] px-3 py-2 text-left">
                    <p class="text-[10px] font-semibold uppercase tracking-widest text-[#4A5568]">
                        ICAO
                    </p>
                    <p class="mt-0.5 font-mono text-base font-bold text-[#1B365D]">
                        {{ $info['icao'] }}
                    </p>
                </div>
            </div>
        </div>

        <div class="h-1 w-full bg-gradient-to-r from-[#C5A059]/60 via-[#C5A059] to-[#C5A059]/60"></div>
    </div>
</div>
