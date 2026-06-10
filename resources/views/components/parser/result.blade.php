@php
    use Carbon\Carbon;
@endphp

@if ($result)
    <aside class="space-y-5">
        <section class="rounded-lg border border-[#1B365D]/15 bg-white shadow-sm">
            <div class="border-b border-[#1B365D]/10 bg-[#0B0E14] px-5 py-4 text-[#F8F9FA]">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#C5A059]">Manifest</p>
                <h2 class="mt-1 text-lg font-bold">Parsed Output</h2>
            </div>

            <div class="space-y-4 p-5">
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-md bg-[#F8F9FA] p-3">
                        <p class="text-xs font-semibold uppercase text-[#4A5568]">Source</p>
                        <p class="mt-1 font-bold text-[#1B365D]">{{ ucfirst($result['source'] ?? 'text') }}</p>
                    </div>
                    <div class="rounded-md bg-[#F8F9FA] p-3">
                        <p class="text-xs font-semibold uppercase text-[#4A5568]">Trip</p>
                        <p class="mt-1 font-bold text-[#1B365D]">{{ $trip['trip_number'] ?? 'Pending' }}</p>
                    </div>
                    <div class="rounded-md bg-[#F8F9FA] p-3">
                        <p class="text-xs font-semibold uppercase text-[#4A5568]">Events</p>
                        <p class="mt-1 font-bold text-[#1B365D]">{{ count($events) }}</p>
                    </div>
                </div>

                @if ($events)
                    <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-[#4A5568]">Download the parsed events as a calendar file.</p>
                        <a href="{{ route('parse.export', ['event_types' => $result['filters'] ?? []]) }}"
                            class="inline-flex items-center justify-center rounded-md bg-[#C5A059] px-4 py-2 text-sm font-semibold text-[#0B0E14] transition hover:bg-[#b6914b]">Download
                            all (.ics)</a>
                    </div>

                    <div class="space-y-3">
                        @foreach ($events as $event)
                            @php
                                $start = \Carbon\Carbon::parse($event['start']);
                                $end = \Carbon\Carbon::parse($event['end']);
                                $sameDay = $start->isSameDay($end);
                                $durationMinutes = $start->diffInMinutes($end);
                                $hours = floor($durationMinutes / 60);
                                $minutes = $durationMinutes % 60;
                                $durationText = $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m";

                                $eventType = strtolower($event['type']);
                                $badgeColor = match($eventType) {
                                    'flight' => 'bg-blue-100 text-blue-900',
                                    'duty' => 'bg-green-100 text-green-900',
                                    'layover' => 'bg-gray-100 text-gray-900',
                                    default => 'bg-[#C5A059]/20 text-[#1B365D]',
                                };
                            @endphp

                            <article class="rounded-md border border-[#1B365D]/10 p-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-semibold text-[#0B0E14]">{{ $event['title'] }}</p>

                                        <p class="mt-1 text-sm text-[#4A5568]">
                                            @if ($sameDay)
                                                {{ $start->format('M j • g:i A') }} – {{ $end->format('g:i A') }}
                                            @else
                                                {{ $start->format('M j, g:i A') }} → {{ $end->format('M j, g:i A') }}
                                            @endif
                                        </p>
                                    </div>

                                    <div class="flex flex-col items-end gap-1">
                                        <span class="shrink-0 rounded-full {{ $badgeColor }} px-2.5 py-1 text-xs font-bold uppercase">
                                            {{ $event['type'] }}
                                        </span>
                                        <p class="text-xs text-[#4A5568]">{{ $durationText }}</p>
                                    </div>
                                </div>

                                <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <a href="{{ route('parse.export.event', ['eventIndex' => $loop->index]) }}"
                                       class="inline-flex h-10 w-10 items-center justify-center rounded-md bg-[#1B365D] text-[#F8F9FA] transition hover:bg-[#142a49]"
                                       title="Download .ics">
                                        <x-heroicon-o-arrow-down-tray class="h-5 w-5" />
                                    </a>
                                </div>
                            </article>
                        @endforeach
                    </div>
            </div>
        @else
            <p class="rounded-md bg-[#F8F9FA] p-4 text-sm text-[#4A5568]">No calendar events matched the current
                filters.</p>
@endif
</div>
</section>

<details class="rounded-lg border border-[#1B365D]/15 bg-white p-5 shadow-sm">
    <summary class="cursor-pointer font-semibold text-[#1B365D]">Raw JSON</summary>
    <pre class="mt-4 max-h-[28rem] overflow-auto rounded-md bg-[#0B0E14] p-4 text-xs text-[#C5A059]">{{ json_encode($result, JSON_PRETTY_PRINT) }}</pre>
</details>
</aside>
@endif
