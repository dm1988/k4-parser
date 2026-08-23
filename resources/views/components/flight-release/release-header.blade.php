@props(['model'])

<section
    {{ $attributes->merge(['class' => 'overflow-hidden rounded-xl border border-[#1B365D]/10 bg-[#F8F9FA] shadow-sm dark:border-slate-700 dark:bg-slate-800']) }}
    aria-label="Release summary"
>
    <div class="flex flex-col gap-4 p-4 transition-all duration-300 sm:p-5 lg:flex-row lg:items-center lg:justify-between lg:gap-8">
        <div class="flex min-w-0 items-center gap-3 lg:min-w-[280px] lg:shrink-0">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-[#1B365D]/10 text-[#1B365D] dark:bg-[#C5A059]/15 dark:text-[#C5A059]">
                <x-heroicon-s-paper-airplane class="h-6 w-6" aria-hidden="true" />
            </div>

            <div class="min-w-0 flex-1">
                <h2 class="truncate font-mono text-lg font-black tracking-[0.04em] text-[#0B0E14] dark:text-white">
                    {{ $model->flightNumber() ?? 'Flight not present' }}
                </h2>
                <p class="truncate text-xs font-semibold text-[#4A5568] dark:text-slate-400">
                    {{ $model->aircraftType() ?? 'Aircraft not present' }}
                    <span aria-hidden="true"> · </span>
                    {{ $model->tailNumber() ?? 'Tail not present' }}
                </p>
            </div>
        </div>

        <div class="flex w-full min-w-0 max-w-xl flex-1 items-center gap-3 rounded-lg bg-slate-50 p-2 backdrop-blur-sm dark:bg-slate-800/80 sm:gap-5 lg:bg-transparent lg:p-0">
            <div class="shrink-0 text-left">
                <p class="font-mono text-xl font-black tracking-[0.06em] text-[#1B365D] dark:text-slate-100 sm:text-2xl">
                    {{ $model->departure() }}
                </p>
                <p class="text-[10px] font-bold uppercase tracking-[0.1em] text-[#4A5568] dark:text-slate-400">
                    {{ $model->releaseHeaderDepartureDate() ?? 'Date not present' }}
                </p>
                <p class="font-mono text-xs font-bold text-blue-700 dark:text-blue-300">
                    @if ($model->releaseHeaderDepartureTime())
                        {{ $model->releaseHeaderDepartureTime() }}<span class="text-[10px]">z</span>
                    @else
                        Time not present
                    @endif
                </p>
            </div>

            <div class="flex min-w-12 flex-1 flex-col items-center gap-1.5">
                <p class="font-mono text-[10px] font-bold uppercase tracking-[0.12em] text-[#4A5568] dark:text-slate-400">
                    {{ $model->duration() !== '' ? $model->duration() : 'Duration not present' }}
                </p>
                <div class="flex w-full items-center" aria-hidden="true">
                    <div class="h-px flex-1 bg-[#1B365D]/20 dark:bg-slate-600"></div>
                    <div class="h-2.5 w-2.5 shrink-0 rounded-full border-2 border-[#C5A059] bg-white dark:bg-slate-800"></div>
                    <div class="h-px flex-1 bg-[#1B365D]/20 dark:bg-slate-600"></div>
                </div>
            </div>

            <div class="shrink-0 text-right">
                <p class="font-mono text-xl font-black tracking-[0.06em] text-[#1B365D] dark:text-slate-100 sm:text-2xl">
                    {{ $model->destination() }}
                </p>
                <p class="text-[10px] font-bold uppercase tracking-[0.1em] text-[#4A5568] dark:text-slate-400">
                    {{ $model->releaseHeaderArrivalDate() ?? 'Date not present' }}
                </p>
                <p class="font-mono text-xs font-bold text-blue-700 dark:text-blue-300">
                    @if ($model->releaseHeaderArrivalTime())
                        {{ $model->releaseHeaderArrivalTime() }}<span class="text-[10px]">z</span>
                    @else
                        Time not present
                    @endif
                </p>
            </div>
        </div>

        @if ($model->releaseRevision())
            <div class="flex justify-end lg:w-28 lg:shrink-0">
                <span class="inline-flex rounded-full bg-slate-200 px-2.5 py-1 font-mono text-[10px] font-bold uppercase tracking-[0.12em] text-slate-700 dark:bg-slate-700 dark:text-slate-200">
                    Revision {{ $model->releaseRevision() }}
                </span>
            </div>
        @endif
    </div>
</section>
