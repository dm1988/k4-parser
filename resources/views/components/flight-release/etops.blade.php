@props(['model'])

<div {{ $attributes->merge(['class' => 'flex min-w-0 flex-col gap-5 p-3 sm:p-5']) }}>
    <section aria-labelledby="etops-source-heading" class="flex min-w-0 flex-col gap-3">
        <div class="flex flex-wrap items-end justify-between gap-2">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#4A5568] dark:text-slate-400">Confirmed release fields</p>
                <h3 id="etops-source-heading" class="text-base font-bold text-[#1B365D] dark:text-slate-100">ETOPS source data</h3>
            </div>
            <p class="text-xs font-semibold text-[#4A5568] dark:text-slate-400">No operational status inferred</p>
        </div>

        <dl class="grid grid-cols-1 gap-2 sm:grid-cols-2">
            <x-flight-release.metric label="Source applicability" :value="$model->etopsApplicabilityLabel()" :mono="false" />
            <x-flight-release.metric label="Confirmed points" :value="$model->overviewEtopsSummary()" empty-text="Not present" />
        </dl>
    </section>

    <section aria-labelledby="etops-boundary-heading" class="flex min-w-0 flex-col gap-3">
        <div>
            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#4A5568] dark:text-slate-400">Route boundary</p>
            <h3 id="etops-boundary-heading" class="text-base font-bold text-[#1B365D] dark:text-slate-100">Boundary points</h3>
        </div>

        @if ($model->etopsBoundaryPoints() === [])
            <p class="rounded-lg border border-dashed border-[#1B365D]/15 bg-[#F8F9FA] p-4 text-sm font-medium text-[#4A5568] dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-400">No EENT or EEXP point was present in the confirmed ETOPS fields.</p>
        @else
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                @foreach ($model->etopsBoundaryPoints() as $point)
                    <x-flight-release.copy-field
                        :id="'etops-boundary-'.$loop->iteration"
                        :label="$point['label']"
                        :value="$point['coordinates']"
                    />
                @endforeach
            </div>
        @endif
    </section>

    <section aria-labelledby="etops-critical-points-heading" class="flex min-w-0 flex-col gap-3">
        <div>
            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#4A5568] dark:text-slate-400">Source sequence</p>
            <h3 id="etops-critical-points-heading" class="text-base font-bold text-[#1B365D] dark:text-slate-100">Equal-time points</h3>
        </div>

        @if ($model->etps() === [])
            <p class="rounded-lg border border-dashed border-[#1B365D]/15 bg-[#F8F9FA] p-4 text-sm font-medium text-[#4A5568] dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-400">No equal-time point was present in the confirmed ETOPS fields.</p>
        @else
            <ol class="grid grid-cols-1 gap-3 xl:grid-cols-2">
                @foreach ($model->etps() as $etp)
                    <li class="min-w-0 overflow-hidden rounded-xl border border-[#1B365D]/10 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                        <header class="flex items-center justify-between gap-3 border-b border-[#1B365D]/10 bg-[#F8F9FA] px-4 py-3 dark:border-slate-700 dark:bg-slate-800">
                            <h4 class="font-mono text-sm font-bold text-[#1B365D] dark:text-sky-300">{{ $etp['label'] }}</h4>
                            <span class="text-[10px] font-bold uppercase tracking-[0.14em] text-[#4A5568] dark:text-slate-400">Point {{ $loop->iteration }}</span>
                        </header>
                        <div class="flex min-w-0 flex-col gap-3 p-4">
                            <x-flight-release.copy-field
                                :id="'etops-point-'.$loop->iteration.'-coordinates'"
                                label="Coordinates"
                                :value="$etp['coordinates']"
                            />
                            <dl class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                @foreach ($model->etpAirports($etp) as $airport)
                                    <x-flight-release.metric :label="'Paired alternate '.($loop->iteration)" :value="$airport" />
                                @endforeach
                            </dl>
                        </div>
                    </li>
                @endforeach
            </ol>
        @endif
    </section>

    <section aria-labelledby="etops-alternates-heading" class="flex min-w-0 flex-col gap-3">
        <div>
            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#4A5568] dark:text-slate-400">Point pairings</p>
            <h3 id="etops-alternates-heading" class="text-base font-bold text-[#1B365D] dark:text-slate-100">ETOPS alternates</h3>
        </div>

        @if ($model->etopsAlternates() === [])
            <p class="rounded-lg border border-dashed border-[#1B365D]/15 bg-[#F8F9FA] p-4 text-sm font-medium text-[#4A5568] dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-400">No alternate airport pairing was present for the confirmed equal-time points.</p>
        @else
            <dl class="grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-4">
                @foreach ($model->etopsAlternates() as $alternate)
                    <x-flight-release.metric :label="'Alternate '.($loop->iteration)" :value="$alternate" />
                @endforeach
            </dl>
        @endif
    </section>

    <section aria-labelledby="etops-scenarios-heading" class="flex min-w-0 flex-col gap-3">
        <div>
            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#4A5568] dark:text-slate-400">Confirmed scenario text</p>
            <h3 id="etops-scenarios-heading" class="text-base font-bold text-[#1B365D] dark:text-slate-100">Source scenarios</h3>
        </div>

        @if ($model->etopsScenarios() === [])
            <p class="rounded-lg border border-dashed border-[#1B365D]/15 bg-[#F8F9FA] p-4 text-sm font-medium text-[#4A5568] dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-400">No supported scenario text was present in the confirmed ETOPS fields.</p>
        @else
            <ul class="flex min-w-0 flex-col gap-2">
                @foreach ($model->etopsScenarios() as $scenario)
                    <li class="flex min-w-0 flex-col gap-1 rounded-lg border border-[#1B365D]/10 bg-[#F8F9FA] p-3 dark:border-slate-700 dark:bg-slate-800/60">
                        @if ($scenario['equalTimePointLabel'])
                            <span class="font-mono text-xs font-bold text-[#1B365D] dark:text-sky-300">{{ $scenario['equalTimePointLabel'] }}</span>
                        @endif
                        <span class="break-words text-sm font-semibold text-[#0B0E14] dark:text-slate-100">{{ $scenario['name'] }}</span>
                    </li>
                @endforeach
            </ul>
        @endif

        <p class="text-xs leading-5 text-[#4A5568] dark:text-slate-400">Diversion calculations, critical fuel values, and source remarks are not shown until supported typed values are present.</p>
    </section>

    <aside class="rounded-lg border border-amber-300/70 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-400/25 dark:bg-amber-400/10 dark:text-amber-100">
        <div class="flex items-start gap-3">
            <x-heroicon-o-exclamation-triangle class="h-5 w-5 shrink-0" />
            <div class="flex min-w-0 flex-col gap-1">
                <p class="font-bold">No approval or suitability determination</p>
                <p class="leading-5">This view repeats confirmed source fields. Point or alternate presence does not establish ETOPS approval, airport suitability, compliance, or dispatchability.</p>
            </div>
        </div>
    </aside>

    <x-flight-release.source-evidence message="ETOPS source fragments and control evidence remain private to this extraction result and are not included in the Livewire snapshot." />
</div>
