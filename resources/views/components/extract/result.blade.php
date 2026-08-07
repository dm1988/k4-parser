@if ($model)
<aside class="space-y-5">
    <section class="rounded-lg border border-[#1B365D]/15 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <!-- REVISED HEADER: Removed harsh black background, matched to the soft slate/navy flight card style -->
        <div class="border-b border-[#1B365D]/10 bg-[#F8FAFD] px-4 py-4 dark:border-slate-700 dark:bg-slate-800 sm:px-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#C5A059]">Manifest</p>
                    <h2 class="mt-1 text-lg font-bold text-[#1B365D] dark:text-slate-100">Extracted Schedule</h2>
                </div>

                @if ($model->exportUrl)
                <a href="{{ $model->exportUrl }}"
                    class="inline-flex items-center justify-center rounded-md bg-[#C5A059] px-4 py-2 text-sm font-semibold text-[#0B0E14] transition hover:bg-[#b6914b]">
                    Download all (.ics)
                </a>
                @endif
            </div>
        </div>

        <div class="space-y-4 p-4 sm:p-6">
            @if ($model->hasError())
            <p class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-400">{{ $model->errorMessage }}
            </p>
            @endif

            <div class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-md border border-[#1B365D]/5 bg-[#F8F9FA] p-3 dark:border-slate-700 dark:bg-slate-800">
                    <p class="text-xs font-semibold uppercase text-[#4A5568] dark:text-slate-400">Source</p>
                    <p class="mt-1 font-bold text-[#1B365D] dark:text-slate-100">{{ $model->sourceLabel }}</p>
                </div>
                <div class="rounded-md border border-[#1B365D]/5 bg-[#F8F9FA] p-3 dark:border-slate-700 dark:bg-slate-800">
                    <p class="text-xs font-semibold uppercase text-[#4A5568] dark:text-slate-400">Trip</p>
                    <p class="mt-1 font-bold text-[#1B365D] dark:text-slate-100">{{ $model->tripNumber }}</p>
                </div>
                <div class="rounded-md border border-[#1B365D]/5 bg-[#F8F9FA] p-3 dark:border-slate-700 dark:bg-slate-800">
                    <p class="text-xs font-semibold uppercase text-[#4A5568] dark:text-slate-400">Events</p>
                    <p class="mt-1 font-bold text-[#1B365D] dark:text-slate-100">{{ $model->eventCount }}</p>
                </div>
            </div>

            @if ($model->exportUrl)
            <div class="space-y-3">
                @foreach ($model->events as $event)
                @if ($event instanceof \App\DTOs\Flight)
                <x-extract.flight-card :model="\App\View\Models\Extract\FlightCardViewModel::fromFlight($event)" />
                @else
                <x-extract.event-card :event="$event" />
                @endif
                @endforeach
            </div>
            @elseif (! $model->hasError())
            <p class="rounded-md bg-[#F8F9FA] p-4 text-sm text-[#4A5568] dark:bg-slate-800 dark:text-slate-400">No calendar events matched the current
                filters.</p>
            @endif
        </div>
    </section>

    @if (auth()->user()?->isAdmin() ?? false)
    <!-- Raw JSON Component aligned with the overall style -->
    <details class="group rounded-lg border border-[#1B365D]/15 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-6">
        <summary
            class="flex cursor-pointer list-none items-center justify-between font-semibold text-[#1B365D] dark:text-slate-200 [&::-webkit-details-marker]:hidden">
            <span>Raw JSON</span>
            <x-heroicon-o-chevron-down class="h-4 w-4 text-[#1B365D] dark:text-slate-300 group-open:hidden" />
            <x-heroicon-o-chevron-up class="hidden h-4 w-4 text-[#1B365D] dark:text-slate-300 group-open:block" />
        </summary>
        <pre
            class="mt-4 max-h-[28rem] overflow-auto rounded-md bg-[#0B0E14] p-4 text-xs text-[#C5A059] font-mono leading-relaxed border border-[#1B365D]/15 shadow-inner">{{ $model->rawJson }}</pre>
    </details>
    @endif
</aside>
@endif
