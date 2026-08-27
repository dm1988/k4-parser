@props(['model'])

<div {{ $attributes->merge(['class' => 'flex min-w-0 flex-col gap-5 p-3 sm:p-5']) }}>
    <section aria-labelledby="weather-source-heading" class="flex min-w-0 flex-col gap-3">
        <div class="flex flex-wrap items-end justify-between gap-2">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#4A5568] dark:text-slate-400">Confirmed release reports</p>
                <h3 id="weather-source-heading" class="text-base font-bold text-[#1B365D] dark:text-slate-100">Airport weather</h3>
            </div>
            <p class="text-xs font-semibold text-[#4A5568] dark:text-slate-400">Raw source text · No conditions inferred</p>
        </div>

        <div class="grid min-w-0 grid-cols-1 gap-4 xl:grid-cols-3">
            @foreach ($model->weatherAirportGroups() as $airportWeather)
                <article class="flex min-w-0 flex-col gap-4 overflow-hidden rounded-xl border border-[#1B365D]/10 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <header class="flex items-center justify-between gap-3 border-b border-[#1B365D]/10 pb-3 dark:border-slate-700">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-[#4A5568] dark:text-slate-400">{{ $airportWeather['role'] }}</p>
                            <h4 class="font-mono text-lg font-black tracking-[0.06em] text-[#1B365D] dark:text-sky-300">
                                {{ $airportWeather['airport'] ?? 'Not listed' }}
                            </h4>
                        </div>
                        <x-heroicon-o-cloud class="h-6 w-6 shrink-0 text-[#C5A059]" aria-hidden="true" />
                    </header>

                    <section class="flex min-w-0 flex-col gap-2" aria-label="{{ $airportWeather['role'] }} METAR reports">
                        <h5 class="text-xs font-bold uppercase tracking-[0.14em] text-[#1B365D] dark:text-slate-200">METAR / SPECI</h5>
                        @forelse ($airportWeather['metars'] as $metar)
                            <pre class="min-w-0 whitespace-pre-wrap break-words rounded-lg bg-[#F8F9FA] p-3 font-mono text-xs leading-5 text-[#0B0E14] dark:bg-slate-800 dark:text-slate-100">{{ $metar }}</pre>
                        @empty
                            <p class="rounded-lg border border-dashed border-[#1B365D]/15 p-3 text-sm text-[#4A5568] dark:border-slate-700 dark:text-slate-400">No METAR or SPECI report was present.</p>
                        @endforelse
                    </section>

                    <section class="flex min-w-0 flex-col gap-2" aria-label="{{ $airportWeather['role'] }} TAF reports">
                        <h5 class="text-xs font-bold uppercase tracking-[0.14em] text-[#1B365D] dark:text-slate-200">TAF</h5>
                        @forelse ($airportWeather['tafs'] as $taf)
                            <pre class="min-w-0 whitespace-pre-wrap break-words rounded-lg bg-[#F8F9FA] p-3 font-mono text-xs leading-5 text-[#0B0E14] dark:bg-slate-800 dark:text-slate-100">{{ $taf }}</pre>
                        @empty
                            <p class="rounded-lg border border-dashed border-[#1B365D]/15 p-3 text-sm text-[#4A5568] dark:border-slate-700 dark:text-slate-400">No TAF report was present.</p>
                        @endforelse
                    </section>
                </article>
            @endforeach
        </div>
    </section>

    <section aria-labelledby="weather-raim-heading" class="flex min-w-0 flex-col gap-2">
        <div>
            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#4A5568] dark:text-slate-400">Release-level source status</p>
            <h3 id="weather-raim-heading" class="text-base font-bold text-[#1B365D] dark:text-slate-100">RAIM</h3>
        </div>

        @if ($model->weatherRaim())
            <pre class="min-w-0 whitespace-pre-wrap break-words rounded-lg border border-[#1B365D]/10 bg-[#F8F9FA] p-4 font-mono text-xs leading-5 text-[#0B0E14] dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">{{ $model->weatherRaim() }}</pre>
        @else
            <p class="rounded-lg border border-dashed border-[#1B365D]/15 bg-[#F8F9FA] p-4 text-sm font-medium text-[#4A5568] dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-400">No supported RAIM status block was present in this release.</p>
        @endif
    </section>

    <aside class="rounded-lg border border-amber-300/70 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-400/25 dark:bg-amber-400/10 dark:text-amber-100">
        <div class="flex items-start gap-3">
            <x-heroicon-o-exclamation-triangle class="h-5 w-5 shrink-0" />
            <div class="flex min-w-0 flex-col gap-1">
                <p class="font-bold">Raw reports only</p>
                <p class="leading-5">This view repeats source METAR, SPECI, TAF, and RAIM text. It does not decode conditions, determine suitability, or provide a dispatch decision.</p>
            </div>
        </div>
    </aside>

    <x-flight-release.source-evidence message="Weather extraction evidence remains private to this result. The operational report text shown above is retained without decoding." />
</div>
