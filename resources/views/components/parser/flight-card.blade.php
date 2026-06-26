@props(['model'])

<article class="overflow-hidden rounded-[1.9rem] border border-[#D8E0EC] bg-[#FCFDFF]"
    style="box-shadow: 0 10px 28px rgba(27, 54, 93, 0.09), 0 2px 6px rgba(27, 54, 93, 0.04);">
    <header class="border-b border-[#D8E0EC] bg-[#F8FAFD] px-8 py-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex min-w-0 items-center gap-3 text-[#1F3C6D]">
                <h3 class="truncate font-mono text-[1.05rem] font-semibold uppercase tracking-[0.16em]">
                    {{ $model->heading() }}
                </h3>

                <span aria-hidden="true" class="h-1.5 w-1.5 rounded-full bg-[#B8C4D7]"></span>

                <p class="text-[1.05rem] font-medium tracking-[0.01em] text-[#4C5C74]">
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

    <div class="px-10 py-9">
        <div class="mb-3 flex items-center justify-between gap-5">
            <div class="flex flex-col">
                <span class="font-mono text-xl font-bold tracking-[0.04em] text-slate-900 sm:text-2xl">
                    {{ $model->originLabel() }}
                </span>
                <span class="mt-2 text-base font-semibold text-slate-800">
                    {{ $model->originTimeLabel() }}
                </span>
            </div>

            <div class="flex min-w-0 flex-1 items-center">
                <div class="h-px flex-1 bg-[#D7E0EC]"></div>

                <div
                    class="mx-4 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#223F72] text-white shadow-[0_4px_10px_rgba(34,63,114,0.18)]">
                    <x-heroicon-s-paper-airplane class="h-4 w-4" />
                </div>

                <div class="h-px flex-1 bg-[#D7E0EC]"></div>
            </div>

            <div class="flex flex-col items-end text-right">
                <span class="font-mono text-xl font-bold tracking-[0.04em] text-slate-900 sm:text-2xl">
                    {{ $model->destinationLabel() }}
                </span>
                <span class="mt-2 text-base font-semibold text-slate-800">
                    {{ $model->destinationTimeLabel() }}
                </span>
            </div>
        </div>

        <div class="mx-auto flex max-w-[34rem] flex-col items-center gap-3">
            <span class="font-mono text-[1.1rem] font-semibold tracking-[0.04em] text-[#58667D]">
                {{ $model->flight->durationLabel }}
            </span>
        </div>

        @if ($model->hasAirportDetails())
        <details class="group mt-8">
            <summary
                class="inline-flex cursor-pointer list-none items-center gap-2 text-[0.95rem] font-semibold text-[#8090A9] transition-colors hover:text-[#5A6C89] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#1B365D]/20 [&::-webkit-details-marker]:hidden">
                Airport details

                <x-heroicon-o-chevron-down class="h-4 w-4 group-open:hidden" />

                <x-heroicon-o-chevron-up class="hidden h-4 w-4 group-open:block" />
            </summary>

            <div class="mt-4 grid grid-cols-1 gap-4 rounded-2xl border border-[#E1E7F0] bg-white p-4 sm:grid-cols-2">
                <div>
                    <span class="mb-2 block font-mono text-[11px] font-bold uppercase tracking-wider text-[#8A97AB]">
                        {{ $model->originIata() }}
                    </span>

                    <div class="flex flex-col gap-1.5">
                        @if ($model->originName())
                        <p class="text-sm font-semibold text-[#111827]">
                            {{ $model->originName() }}
                        </p>
                        @endif

                        @if ($model->originIcao())
                        <div class="flex items-center gap-1.5 text-sm text-[#5E6B80]">
                            <x-heroicon-o-signal class="h-3.5 w-3.5 shrink-0 text-[#8A97AB]" />

                            <span class="font-mono">
                                ICAO: {{ $model->originIcao() }}
                            </span>
                        </div>
                        @endif

                        @if ($model->originCity() || $model->originCountryCode())
                        <div class="flex items-center gap-1.5 text-sm text-[#5E6B80]">
                            <x-heroicon-o-map-pin class="h-3.5 w-3.5 shrink-0 text-[#8A97AB]" />

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

                <div class="border-t border-[#E8EDF5] pt-4 sm:border-l sm:border-t-0 sm:pl-4 sm:pt-0">
                    <span class="mb-2 block font-mono text-[11px] font-bold uppercase tracking-wider text-[#8A97AB]">
                        {{ $model->destinationIata() }}
                    </span>

                    <div class="flex flex-col gap-1.5">
                        @if ($model->destinationName())
                        <p class="text-sm font-semibold text-[#111827]">
                            {{ $model->destinationName() }}
                        </p>
                        @endif

                        @if ($model->destinationIcao())
                        <div class="flex items-center gap-1.5 text-sm text-[#5E6B80]">
                            <x-heroicon-o-signal class="h-3.5 w-3.5 shrink-0 text-[#8A97AB]" />

                            <span class="font-mono">
                                ICAO: {{ $model->destinationIcao() }}
                            </span>
                        </div>
                        @endif

                        @if ($model->destinationCity() || $model->destinationCountryCode())
                        <div class="flex items-center gap-1.5 text-sm text-[#5E6B80]">
                            <x-heroicon-o-map-pin class="h-3.5 w-3.5 shrink-0 text-[#8A97AB]" />

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
            {{-- @if ($model->hasAirportDetails())
            <x-parser.flight-card.dropdown icon="heroicon-o-map-pin" title="Airports">
                @include('parser.partials.flight-card.airport-details', [
                'model' => $model,
                ])
            </x-parser.flight-card.dropdown>
            @endif

            <x-parser.flight-card.dropdown icon="heroicon-o-paper-airplane" title="Flight">
                @include('parser.partials.flight-card.flight-details', [
                'model' => $model,
                ])
            </x-parser.flight-card.dropdown>

            <x-parser.flight-card.dropdown icon="heroicon-o-user-group" title="Crew" align="right">
                @include('parser.partials.flight-card.crew-details', [
                'model' => $model,
                ])
            </x-parser.flight-card.dropdown> --}}
            
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
            <x-parser.flight-card.accordion icon="heroicon-o-user-group" title="Crew" align="right">
                @include('parser.partials.flight-card.crew-details', [
                'model' => $model,
                ])
            </x-parser.flight-card.accordion>
            @endif
        </div>
    </div>

    <footer class="flex items-center justify-between border-t border-[#D8E0EC] bg-[#F8FAFD] px-8 py-5">
        <div class="flex items-center gap-3">
            @if ($model->flight->tailNumber)
            <span class="text-sm font-semibold uppercase tracking-[0.12em] text-[#7B889D]">
                Tail
            </span>

            <span class="font-mono text-[1.15rem] font-semibold text-[#161B25]">
                {{ $model->flight->tailNumber }}
            </span>
            @endif
        </div>

        <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <a href="{{ $model->flight->downloadUrl }}"
                class="inline-flex h-10 w-10 items-center justify-center rounded-md bg-[#1B365D] text-[#F8F9FA] transition hover:bg-[#142a49]"
                title="Download .ics">
                <x-heroicon-o-arrow-down-tray class="h-5 w-5" />
            </a>
        </div>
    </footer>
</article>
