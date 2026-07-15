@props(['model'])

<article class="overflow-hidden rounded-lg border border-[#1B365D]/15 bg-white shadow-sm">
    <!-- REVISED HEADER: Changed background to clean, light blue-gray tint with navy text -->
    <header class="border-b border-[#1B365D]/10 bg-[#F8FAFD] px-6 py-4 text-[#1B365D]">
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
            <div class="flex flex-col">
                <span class="font-mono text-xl font-bold tracking-[0.04em] text-[#1B365D] sm:text-2xl">
                    {{ $model->originLabel() }}
                </span>
                <span class="mt-2 text-base font-semibold text-[#0B0E14]">
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

            <div class="flex flex-col items-end text-right">
                <span class="font-mono text-xl font-bold tracking-[0.04em] text-[#1B365D] sm:text-2xl">
                    {{ $model->destinationLabel() }}
                </span>
                <span class="mt-2 text-base font-semibold text-[#0B0E14]">
                    {{ $model->destinationCardTimeLabel() }}
                </span>
            </div>
        </div>

        <div class="mx-auto flex max-w-[34rem] flex-col items-center gap-3">
            <span class="font-mono text-[1.1rem] font-semibold tracking-[0.04em] text-[#4A5568]">
                {{ $model->flight->durationLabel }}
            </span>
        </div>

        @if ($model->hasAirportDetails())
        <details class="group mt-8">
            <summary
                class="inline-flex cursor-pointer list-none items-center gap-2 text-[0.95rem] font-semibold text-[#1B365D] transition-colors hover:text-[#142a49] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#1B365D]/20 [&::-webkit-details-marker]:hidden">
                Airport details

                <x-heroicon-o-chevron-down class="h-4 w-4 group-open:hidden" />

                <x-heroicon-o-chevron-up class="hidden h-4 w-4 group-open:block" />
            </summary>

            <div class="mt-4 grid grid-cols-1 gap-4 rounded-lg border border-[#1B365D]/10 bg-[#F8F9FA] p-4 sm:grid-cols-2">
                <div>
                    <span class="mb-2 block font-mono text-[11px] font-bold uppercase tracking-wider text-[#4A5568]">
                        {{ $model->originIata() }}
                    </span>

                    <div class="flex flex-col gap-1.5">
                        @if ($model->originName())
                        <p class="text-sm font-semibold text-[#0B0E14]">
                            {{ $model->originName() }}
                        </p>
                        @endif

                        @if ($model->originIcao())
                        <div class="flex items-center gap-1.5 text-sm text-[#4A5568]">
                            <x-heroicon-o-signal class="h-3.5 w-3.5 shrink-0 text-[#C5A059]" />

                            <span class="font-mono">
                                ICAO: {{ $model->originIcao() }}
                            </span>
                        </div>
                        @endif

                        @if ($model->originCity() || $model->originCountryCode())
                        <div class="flex items-center gap-1.5 text-sm text-[#4A5568]">
                            <x-heroicon-o-map-pin class="h-3.5 w-3.5 shrink-0 text-[#C5A059]" />

                            <span>
                                {{ $model->originCity() }}

                                @if ($model->originCity() && $model->originCountryCode())
                                ,
                                @endif

                                {{ $model->originCountryCode() }}
                            </span>
                        </div>
                        @endif
                    </div>
                </div>

                <div class="border-t border-[#1B365D]/10 pt-4 sm:border-l sm:border-t-0 sm:pl-4 sm:pt-0">
                    <span class="mb-2 block font-mono text-[11px] font-bold uppercase tracking-wider text-[#4A5568]">
                        {{ $model->destinationIata() }}
                    </span>

                    <div class="flex flex-col gap-1.5">
                        @if ($model->destinationName())
                        <p class="text-sm font-semibold text-[#0B0E14]">
                            {{ $model->destinationName() }}
                        </p>
                        @endif

                        @if ($model->destinationIcao())
                        <div class="flex items-center gap-1.5 text-sm text-[#4A5568]">
                            <x-heroicon-o-signal class="h-3.5 w-3.5 shrink-0 text-[#C5A059]" />

                            <span class="font-mono">
                                ICAO: {{ $model->destinationIcao() }}
                            </span>
                        </div>
                        @endif

                        @if ($model->destinationCity() || $model->destinationCountryCode())
                        <div class="flex items-center gap-1.5 text-sm text-[#4A5568]">
                            <x-heroicon-o-map-pin class="h-3.5 w-3.5 shrink-0 text-[#C5A059]" />

                            <span>
                                {{ $model->destinationCity() }}

                                @if ($model->destinationCity() && $model->destinationCountryCode())
                                ,
                                @endif

                                {{ $model->destinationCountryCode() }}
                            </span>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </details>
        @endif
        
        <div class="mt-8 flex flex-wrap items-center gap-2">
            @if ($model->hasAirportDetails())
            <x-parser.flight-card.accordion icon="heroicon-o-map-pin" title="Airports">
                @include('parser.partials.flight-card.airport-details', [
                'model' => $model,
                ])
            </x-parser.flight-card.accordion>
            @endif

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

    <footer class="flex items-center justify-between border-t border-[#1B365D]/10 bg-[#F8F9FA] px-6 py-4">
        <div class="flex items-center gap-3">
            @if ($model->flight->tailNumber)
            <span class="text-sm font-semibold uppercase tracking-[0.12em] text-[#4A5568]">
                Tail
            </span>

            <span class="font-mono text-[1.15rem] font-semibold text-[#1B365D]">
                {{ $model->flight->tailNumber }}
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