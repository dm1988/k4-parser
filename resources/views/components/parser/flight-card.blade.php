@props(['model'])

<article class="overflow-visible rounded-lg border border-[#1B365D]/15 bg-white shadow-sm">
    <!-- REVISED HEADER: Changed background to clean, light blue-gray tint with navy text -->
    <header class="rounded-t-lg border-b border-[#1B365D]/10 bg-[#E9F0F8] px-6 py-4 text-[#1B365D]">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex min-w-0 items-center gap-3">
                <h3 class="truncate font-mono text-[1.05rem] font-semibold uppercase tracking-[0.16em] text-[#1B365D]">
                    {{ $model->heading() }}
                </h3>

                <!-- Gold separator dot matches the brand colors -->
                <span aria-hidden="true" class="h-1.5 w-1.5 rounded-full bg-[#C5A059]"></span>

                <p class="text-[1.05rem] font-medium tracking-[0.01em] text-[#4A5568]">
                    {{ $model->headingDateLabel() }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                <span
                    class="inline-flex shrink-0 items-center gap-1 rounded-full {{ $model->flight->badgeColor }} px-2.5 py-1 text-xs font-bold uppercase"
                    title="{{ $model->flight->typeLabel }}">
                    <x-dynamic-component :component="$model->flight->typeIcon" class="h-3.5 w-3.5" />
                    {{ $model->flight->typeLabel }}
                </span>
            </div>
        </div>
    </header>

    <div class="px-6 py-6">
        <div class="mb-3 flex items-center justify-between gap-5">
            <div class="flex flex-col gap-2">
                @if ($model->originAirportInfo())
                    <x-parser.flight-card.airport-popover :info="$model->originAirportInfo()" align="left" />
                @else
                    <span class="font-mono text-xl font-bold tracking-[0.04em] text-[#1B365D] sm:text-2xl">
                        {{ $model->originLabel() }}
                    </span>
                @endif

                <span class="text-base font-semibold text-[#0B0E14]">
                    {{ $model->originCardTimeLabel() }}
                </span>
            </div>

            <div class="flex min-w-0 flex-1 items-center">
                <div class="h-px flex-1 bg-[#1B365D]/15"></div>

                <div
                    class="mx-4 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#1B365D] text-white shadow-sm">
                    <x-heroicon-s-paper-airplane class="h-4 w-4" />
                </div>

                <div class="h-px flex-1 bg-[#1B365D]/15"></div>
            </div>

            <div class="flex flex-col items-end gap-2 text-right">
                @if ($model->destinationAirportInfo())
                    <x-parser.flight-card.airport-popover :info="$model->destinationAirportInfo()" align="right" />
                @else
                    <span class="font-mono text-xl font-bold tracking-[0.04em] text-[#1B365D] sm:text-2xl">
                        {{ $model->destinationLabel() }}
                    </span>
                @endif

                <span class="text-base font-semibold text-[#0B0E14]">
                    {{ $model->destinationCardTimeLabel() }}
                </span>
            </div>
        </div>

        <div class="mx-auto flex max-w-[34rem] flex-col items-center gap-3">
            <span class="font-mono text-[1.1rem] font-semibold tracking-[0.04em] text-[#4A5568]">
                {{ $model->flight->durationLabel }}
            </span>
        </div>

        <div class="mt-8 flex flex-wrap items-center gap-2">
            <x-parser.flight-card.accordion icon="heroicon-o-paper-airplane" title="Flight">
                @include('parser.partials.flight-card.flight-details', [
                'model' => $model,
                ])
            </x-parser.flight-card.accordion>

            @if ($model->hasCrewDetails())
            <x-parser.flight-card.accordion icon="heroicon-o-user-group" title="Crew">
                @include('parser.partials.flight-card.crew-details', [
                'model' => $model,
                ])
            </x-parser.flight-card.accordion>
            @endif
        </div>
    </div>

    <footer class="flex items-center justify-between rounded-b-lg border-t border-[#1B365D]/10 bg-[#F8F9FA] px-6 py-4">
        <div class="flex items-center gap-3">
            @if ($model->hasFooterContext())
            <span class="text-sm font-semibold uppercase tracking-[0.12em] text-[#4A5568]">
                {{ $model->footerContextLabel() }}
            </span>

            <span class="font-mono text-[1.15rem] font-semibold text-[#1B365D]">
                {{ $model->footerContextValue() }}
            </span>
            @endif
        </div>

        <div class="flex items-center gap-2">
            @if ($model->showsDutyDownload() && $model->dutyDownloadUrl())
            <a href="{{ $model->dutyDownloadUrl() }}"
                class="inline-flex h-10 w-10 items-center justify-center rounded-md bg-[#4C5C74] text-[#F8F9FA] transition hover:bg-[#374357]"
                title="Download duty .ics">
                <x-heroicon-o-briefcase class="h-5 w-5" />
            </a>
            @endif

            <a href="{{ $model->flight->downloadUrl }}"
                class="inline-flex h-10 w-10 items-center justify-center rounded-md bg-[#1B365D] text-[#F8F9FA] transition hover:bg-[#142a49]"
                title="Download .ics">
                <x-heroicon-o-arrow-down-tray class="h-5 w-5" />
            </a>
        </div>
    </footer>
</article>
