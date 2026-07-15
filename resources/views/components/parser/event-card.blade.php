@props(['event'])

@php
    $headerDateLabel = method_exists($event, 'headingDateLabel')
        ? $event->headingDateLabel()
        : (filled($event->start ?? null)
            ? \Carbon\CarbonImmutable::parse($event->start)->format('M j')
            : $event->scheduleLabel);
@endphp

<article class="overflow-hidden rounded-lg border border-[#1B365D]/15 bg-white shadow-sm">
    <!-- HEADER: Aligns with the Flight Card visual system -->
    <header class="border-b border-[#1B365D]/10 bg-[#F8FAFD] px-6 py-4 text-[#1B365D]">
        <div class="flex flex-nowrap items-center justify-between gap-3">
            <div class="flex min-w-0 items-center gap-3">
                <!-- Icon and Title -->
                <div class="flex items-center gap-2 min-w-0">
                    <h3 class="truncate font-mono text-[1.05rem] font-semibold uppercase tracking-[0.12em] text-[#1B365D]">
                        {{ $event->title }}
                    </h3>
                </div>

                <!-- Brand gold separator dot -->
                <span aria-hidden="true" class="h-1.5 w-1.5 rounded-full bg-[#C5A059]"></span>

                <p class="text-[1.05rem] font-medium tracking-[0.01em] text-[#4A5568] truncate">
                    {{ $headerDateLabel }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                <span
                    class="inline-flex shrink-0 items-center gap-1 rounded-full {{ $event->badgeColor }} px-2.5 py-1 text-xs font-bold uppercase"
                    title="{{ $event->typeDescription }}">
                    {{ $event->typeLabel }}
                </span>
            </div>
        </div>
    </header>

    <!-- BODY: Displays time, duration, tail number, and action buttons -->
    <div class="px-6 py-5">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            
            <!-- Left Side: Schedule and Context -->
            <div class="space-y-2">
                <div class="flex flex-col">
                    <!-- Clean, secondary text styling for times -->
                    <span class="text-base text-[#0B0E14]">
                        {{ $event->scheduleLabel }}
                    </span>
                    <span class="mt-1 font-mono text-sm text-[#4A5568]">
                        Duration: {{ $event->durationLabel }}
                    </span>
                </div>

                @if ($event->tailNumber)
                <div class="inline-flex items-center gap-1.5 pt-1">
                    <span class="text-xs font-semibold uppercase tracking-wider text-[#4A5568]/70">Tail:</span>
                    <span class="rounded border border-[#1B365D]/10 bg-[#F8FAFD] px-2 py-0.5 font-mono text-xs font-semibold text-[#1B365D]">
                        {{ $event->tailNumber }}
                    </span>
                </div>
                @endif
            </div>

            <!-- Right Side: Actions matching standard action footprints -->
            <div class="flex items-center justify-end border-t border-[#1B365D]/5 pt-3 sm:border-t-0 sm:pt-0">
                <a href="{{ $event->downloadUrl }}"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-md bg-[#1B365D] text-[#F8F9FA] transition hover:bg-[#142a49]"
                    title="Download .ics">
                    <x-heroicon-o-arrow-down-tray class="h-5 w-5" />
                </a>
            </div>
        </div>
    </div>
</article>
