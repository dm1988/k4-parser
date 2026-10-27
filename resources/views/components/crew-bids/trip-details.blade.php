@props(['trip'])
<div {{ $attributes->class('mt-3 space-y-3 text-sm') }}>
    <p class="break-words font-mono font-semibold">{{ $trip['route_label'] }}</p>
    <x-crew-bids.leg-timeline :trip="$trip" />
    <dl class="grid grid-cols-1 gap-2 sm:grid-cols-2">
        <div><dt class="text-slate-500 dark:text-slate-400">Report · UTC</dt><dd class="font-mono">{{ $trip['start_date'] }} {{ $trip['report'] ?? 'Not present' }}</dd></div>
        <div><dt class="text-slate-500 dark:text-slate-400">Release · UTC</dt><dd class="font-mono">{{ $trip['end_date'] }} {{ $trip['release'] }}</dd></div>
        <div><dt class="text-slate-500 dark:text-slate-400">Layover airports</dt><dd class="font-mono">{{ $trip['layover_label'] }}</dd></div>
        <div><dt class="text-slate-500 dark:text-slate-400">Minimum source rest · h:mm</dt><dd class="font-mono">{{ $trip['minimum_rest'] ?? 'Not present in imported trip data' }}</dd></div>
    </dl>
    @if ($trip['layovers'] !== [])
        <div class="rounded-md border border-slate-200 p-3 dark:border-slate-700">
            <h5 class="font-semibold">Layovers · arrival to next departure</h5>
            <ul class="mt-1 space-y-1">@foreach ($trip['layovers'] as $layover)<li><span class="font-mono">{{ $layover['airport'] }}</span> Layover: <span class="font-mono">{{ $layover['duration'] }}</span></li>@endforeach</ul>
            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Calculated from scheduled UTC times. Source rest is shown separately.</p>
        </div>
    @endif
    <ul class="space-y-2" aria-label="Legs for trip {{ $trip['id'] }}">
        @foreach ($trip['legs'] as $leg)
            <li class="rounded-md bg-slate-50 p-3 dark:bg-slate-800">
                <p class="font-mono font-semibold">{{ $leg['origin'] }} → {{ $leg['destination'] }}{{ $leg['deadhead'] ? ' · Deadhead' : '' }}</p>
                @if ($leg['flight'] ?? null)<p class="font-mono text-xs">{{ $leg['flight'] }}</p>@endif
                <p class="mt-1 text-xs">Departure <span class="font-mono">{{ $leg['date'] }} {{ $leg['departure'] }}</span> UTC</p>
                <p class="text-xs">Arrival <span class="font-mono">{{ $leg['arrival_date'] }} {{ $leg['arrival'] }}</span> UTC</p>
                <p class="mt-1 text-xs">Block (h:mm): <span class="font-mono">{{ $leg['block'] ?? 'Not recorded for this transport leg' }}</span>@if ($leg['block_basis'] === 'calculated') · calculated from scheduled UTC times @elseif ($leg['block_basis'] === 'source') · source @endif</p>
                <p class="text-xs">Scheduled elapsed: <span class="font-mono">{{ $leg['elapsed_label'] }}</span></p>
                @if ($leg['connection_status'] === 'layover')<p class="text-xs">{{ $leg['destination'] }} Layover: <span class="font-mono">{{ $leg['layover_label'] }}</span> · arrival to next departure</p>
                @elseif ($leg['connection_status'] === 'overlap')<p class="text-xs text-amber-800 dark:text-amber-300">Layover unavailable: consecutive leg times overlap.</p>
                @elseif ($leg['connection_status'] === 'airport_change')<p class="text-xs text-amber-800 dark:text-amber-300">Layover unavailable: next leg departs from a different airport.</p>@endif
                @if ($leg['rest_after'] ?? null)<p class="text-xs">Rest at {{ $leg['destination'] }} (h:mm): <span class="font-mono">{{ $leg['rest_after'] }}</span></p>@endif
            </li>
        @endforeach
    </ul>
    <p class="text-xs text-slate-500 dark:text-slate-400">Hotel details: Not present in imported trip data.</p>
</div>
