@props(['model'])

<div {{ $attributes->merge(['class' => 'flex min-w-0 flex-col gap-5 p-3 sm:p-5']) }}>
    <section aria-labelledby="approved-slot-times-heading" class="flex min-w-0 flex-col gap-3">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between sm:gap-4">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#4A5568] dark:text-slate-400">Confirmed release values</p>
                <h3 id="approved-slot-times-heading" class="text-base font-bold text-[#1B365D] dark:text-slate-100">Approved slot times</h3>
            </div>
            <p class="text-xs font-semibold text-[#4A5568] dark:text-slate-400">All displayed times are UTC</p>
        </div>

        <ol class="grid grid-cols-1 gap-3 lg:grid-cols-2">
            @foreach ($model->slotTimes() as $slot)
                <li class="overflow-hidden rounded-xl border border-[#1B365D]/10 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <div class="flex items-center justify-between gap-3 border-b border-[#1B365D]/10 bg-[#F8F9FA] px-4 py-3 dark:border-slate-700 dark:bg-slate-800">
                        <div class="flex min-w-0 items-center gap-2">
                            <span class="rounded-full bg-[#1B365D] px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.14em] text-white dark:bg-blue-500/20 dark:text-blue-200">{{ $slot['direction'] }}</span>
                            <span class="font-mono text-sm font-bold text-[#1B365D] dark:text-slate-100">{{ $slot['airport'] }}</span>
                        </div>
                        <span class="text-[10px] font-bold uppercase tracking-[0.16em] text-[#B8860B] dark:text-amber-300">{{ $slot['timeBasis'] }}</span>
                    </div>

                    <dl class="grid grid-cols-2 gap-3 p-4">
                        <div class="flex flex-col gap-1">
                            <dt class="text-[10px] font-bold uppercase tracking-[0.14em] text-[#4A5568] dark:text-slate-400">Date</dt>
                            <dd class="font-mono text-sm font-semibold tabular-nums text-[#0B0E14] dark:text-slate-100">{{ $slot['date'] }}</dd>
                        </div>
                        <div class="flex flex-col gap-1 text-right">
                            <dt class="text-[10px] font-bold uppercase tracking-[0.14em] text-[#4A5568] dark:text-slate-400">Time (UTC)</dt>
                            <dd class="font-mono text-lg font-bold tabular-nums text-[#1B365D] dark:text-blue-200">{{ $slot['time'] }}</dd>
                        </div>
                        @if ($slot['tolerance'] !== null)
                            <div class="col-span-2 flex flex-col gap-1 border-t border-[#1B365D]/10 pt-3 dark:border-slate-700">
                                <dt class="text-[10px] font-bold uppercase tracking-[0.14em] text-[#4A5568] dark:text-slate-400">Approved window</dt>
                                <dd class="flex flex-wrap items-baseline justify-between gap-2 font-mono text-sm font-semibold tabular-nums text-[#0B0E14] dark:text-slate-100">
                                    <span>{{ $slot['window'] }}</span>
                                    <span class="text-[#B8860B] dark:text-amber-300">{{ $slot['tolerance'] }}</span>
                                </dd>
                            </div>
                        @endif
                        @if ($slot['comparison'] !== null)
                            <div class="col-span-2 flex flex-col gap-2 border-t border-[#1B365D]/10 pt-3 dark:border-slate-700">
                                <div class="flex flex-wrap items-baseline justify-between gap-2">
                                    <dt class="text-[10px] font-bold uppercase tracking-[0.14em] text-[#4A5568] dark:text-slate-400">Planned arrival comparison</dt>
                                    <dd class="font-mono text-xs font-semibold tabular-nums text-[#0B0E14] dark:text-slate-100">{{ $slot['plannedArrival'] }}</dd>
                                </div>
                                <div class="relative h-3 rounded-full bg-[#1B365D]/10 dark:bg-slate-700" aria-hidden="true">
                                    <div class="absolute inset-y-0 left-1/4 right-1/4 rounded-full bg-[#B8860B]/35 dark:bg-amber-400/30"></div>
                                    <div class="absolute -top-1 h-5 w-1 rounded-full bg-[#1B365D] dark:bg-blue-300" style="left: calc({{ $slot['plannedPosition'] }}% - 2px)"></div>
                                </div>
                                <div class="flex justify-between gap-3 text-[10px] font-semibold text-[#4A5568] dark:text-slate-400">
                                    <span>Earlier</span>
                                    <span class="text-center text-[#B8860B] dark:text-amber-300">Confirmed window</span>
                                    <span>Later</span>
                                </div>
                                <dd class="text-xs font-semibold text-[#1B365D] dark:text-blue-200">{{ $slot['comparison'] }}</dd>
                            </div>
                        @endif
                    </dl>
                </li>
            @endforeach
        </ol>
    </section>

    @if ($model->slotSourceText() !== null)
        <details class="group rounded-xl border border-[#1B365D]/10 bg-[#F8F9FA] dark:border-slate-700 dark:bg-slate-800/60">
            <summary class="cursor-pointer rounded-xl px-4 py-3 text-xs font-bold uppercase tracking-[0.16em] text-[#1B365D] outline-none focus-visible:ring-2 focus-visible:ring-[#B8860B] focus-visible:ring-offset-2 dark:text-slate-200 dark:focus-visible:ring-offset-slate-900">Extracted slot text</summary>
            <p class="whitespace-pre-wrap break-words border-t border-[#1B365D]/10 px-4 py-3 font-mono text-xs leading-relaxed text-[#0B0E14] dark:border-slate-700 dark:text-slate-100">{{ $model->slotSourceText() }}</p>
        </details>
    @endif

    <x-flight-release.source-evidence message="Approved slots and confirmed tolerance windows are repeated from the flight release with complete UTC dates. Local times, permits, and statuses are not inferred." />
</div>
