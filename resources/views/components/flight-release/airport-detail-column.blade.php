@props([
    'label',
    'code' => null,
    'airport' => null,
    'fallback' => null,
    'muted' => false,
])

<div @class([
    'flex min-w-0 flex-col gap-1 px-4 py-3',
    'bg-amber-50/40' => $muted,
])>
    <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#4A5568] dark:text-slate-400">{{ $label }}</p>

    @if ($code)
        <p class="break-words font-mono text-lg font-bold text-[#1B365D] dark:text-slate-100">{{ $code }}</p>
    @endif

    @if ($airport)
        <p class="break-words text-xs font-semibold leading-snug text-[#0B0E14] dark:text-slate-100">{{ $airport['name'] }}</p>
        <p class="break-words text-[11px] leading-relaxed text-[#4A5568] dark:text-slate-400">{{ $airport['location'] }}</p>
        <div class="font-mono text-[11px] leading-relaxed text-[#4A5568]/70 dark:text-slate-500">
            <p>ICAO {{ $airport['icao'] }}</p>
            <p>IATA {{ $airport['iata'] }}</p>
        </div>
    @elseif ($fallback)
        <p class="text-[11px] leading-relaxed text-[#4A5568] dark:text-slate-400">{{ $fallback }}</p>
    @endif
</div>
