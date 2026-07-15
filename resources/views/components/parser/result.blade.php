@if ($model)
<aside class="space-y-5">
    <section class="rounded-lg border border-[#1B365D]/15 bg-white shadow-sm">
        <!-- REVISED HEADER: Removed harsh black background, matched to the soft slate/navy flight card style -->
        <div class="border-b border-[#1B365D]/10 bg-[#F8FAFD] px-5 py-4">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#C5A059]">Manifest</p>
            <h2 class="mt-1 text-lg font-bold text-[#1B365D]">Parsed Output</h2>
        </div>

        <div class="space-y-4 p-5">
            @if ($model->hasError())
            <p class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ $model->errorMessage }}
            </p>
            @endif

            <div class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-md bg-[#F8F9FA] p-3 border border-[#1B365D]/5">
                    <p class="text-xs font-semibold uppercase text-[#4A5568]">Source</p>
                    <p class="mt-1 font-bold text-[#1B365D]">{{ $model->sourceLabel }}</p>
                </div>
                <div class="rounded-md bg-[#F8F9FA] p-3 border border-[#1B365D]/5">
                    <p class="text-xs font-semibold uppercase text-[#4A5568]">Trip</p>
                    <p class="mt-1 font-bold text-[#1B365D]">{{ $model->tripNumber }}</p>
                </div>
                <div class="rounded-md bg-[#F8F9FA] p-3 border border-[#1B365D]/5">
                    <p class="text-xs font-semibold uppercase text-[#4A5568]">Events</p>
                    <p class="mt-1 font-bold text-[#1B365D]">{{ $model->eventCount }}</p>
                </div>
            </div>

            @if ($model->exportUrl)
            <div
                class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-[#1B365D]/10 pb-4">
                <p class="text-sm text-[#4A5568]">Download the parsed events as a calendar file.</p>
                <a href="{{ $model->exportUrl }}"
                    class="inline-flex items-center justify-center rounded-md bg-[#C5A059] px-4 py-2 text-sm font-semibold text-[#0B0E14] transition hover:bg-[#b6914b]">
                    Download all (.ics)
                </a>
            </div>

            <div class="space-y-3">
                @foreach ($model->events as $event)
                @if ($event instanceof \App\DTOs\Flight)
                <x-parser.flight-card :model="\App\View\Models\Parser\FlightCardViewModel::fromFlight($event)" />
                @else
                <x-parser.event-card :event="$event" />
                @endif
                @endforeach
            </div>
            @elseif (! $model->hasError())
            <p class="rounded-md bg-[#F8F9FA] p-4 text-sm text-[#4A5568]">No calendar events matched the current
                filters.</p>
            @endif
        </div>
    </section>

    <!-- Raw JSON Component aligned with the overall style -->
    <details class="group rounded-lg border border-[#1B365D]/15 bg-white p-5 shadow-sm">
        <summary
            class="flex cursor-pointer list-none items-center justify-between font-semibold text-[#1B365D] [&::-webkit-details-marker]:hidden">
            <span>Raw JSON</span>
            <x-heroicon-o-chevron-down class="h-4 w-4 text-[#1B365D] group-open:hidden" />
            <x-heroicon-o-chevron-up class="hidden h-4 w-4 text-[#1B365D] group-open:block" />
        </summary>
        <pre
            class="mt-4 max-h-[28rem] overflow-auto rounded-md bg-[#0B0E14] p-4 text-xs text-[#C5A059] font-mono leading-relaxed border border-[#1B365D]/15 shadow-inner">{{ $model->rawJson }}</pre>
    </details>
</aside>
@endif
