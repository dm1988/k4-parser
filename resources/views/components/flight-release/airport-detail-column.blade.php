@props([
    'label',
    'airport' => null,
    'fallback' => null,
    'muted' => false,
])

<div @class([
    'flex min-w-0 flex-col gap-1 px-4 py-3',
    'bg-amber-50/40' => $muted,
])>
    <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#4A5568]">{{ $label }}</p>

    @if ($airport)
        <p class="break-words text-xs font-semibold leading-snug text-[#0B0E14]">{{ $airport['name'] }}</p>
        <p class="break-words text-[11px] leading-relaxed text-[#4A5568]">{{ $airport['location'] }}</p>
        <div class="font-mono text-[11px] leading-relaxed text-[#4A5568]/70">
            <p>ICAO {{ $airport['icao'] }}</p>
            <p>IATA {{ $airport['iata'] }}</p>
        </div>
    @elseif ($fallback)
        <p class="text-[11px] leading-relaxed text-[#4A5568]">{{ $fallback }}</p>
    @endif
</div>
