@props(['field'])

<article {{ $attributes->merge(['class' => 'cc-weight-field flex min-w-0 flex-col gap-3 rounded-lg border border-[#1B365D]/10 bg-white p-4 dark:border-slate-700 dark:bg-slate-900']) }}>
    <div class="flex flex-wrap items-start justify-between gap-2">
        <h3 class="min-w-0 text-xs font-bold uppercase tracking-[0.14em] text-[#1B365D] [overflow-wrap:anywhere] dark:text-slate-200">
            {{ $field->label }}
        </h3>
        @if ($field->showsSourceStatusBadge())
            <span class="inline-flex shrink-0 rounded-full px-2 py-1 text-[9px] font-bold uppercase tracking-[0.12em] {{ $field->sourceStatus->badgeClasses() }}">
                {{ $field->sourceStatus->label() }}
            </span>
        @elseif ($field->hasUtilizationComparison())
            <span class="cc-weight-percentage-header font-mono text-[11px] font-semibold text-slate-600 dark:text-slate-400">
                {{ $field->utilizationPercentLabel() }}
            </span>
        @endif
    </div>

    <div class="flex-1 {{ $field->valueLayoutClasses() }}">
        <div class="min-w-0">
            <p class="flex flex-wrap items-baseline gap-1.5 font-mono text-[#0B0E14] dark:text-slate-100">
                <span class="min-w-0 text-2xl font-black tracking-tight [overflow-wrap:anywhere]">{{ $field->plannedAmountLabel() }}</span>
                @if ($field->plannedUnit() !== null)
                    <span class="text-[10px] font-bold tracking-[0.12em] text-[#4A5568] dark:text-slate-400">{{ $field->plannedUnit() }}</span>
                @endif
            </p>
        </div>

        @if ($field->showsStandaloneLimit())
            <div class="min-w-0 text-right">
                <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-[#4A5568] dark:text-slate-400">Structural limit</p>
                <p class="mt-1 flex flex-wrap items-baseline justify-end gap-1.5 font-mono text-[#0B0E14] dark:text-slate-100">
                    <span class="min-w-0 text-2xl font-black tracking-tight [overflow-wrap:anywhere]">{{ $field->limitAmountLabel() }}</span>
                    @if ($field->limitUnit() !== null)
                        <span class="text-[10px] font-bold tracking-[0.12em] text-[#4A5568] dark:text-slate-400">{{ $field->limitUnit() }}</span>
                    @endif
                </p>
            </div>
        @endif
    </div>

    @if ($field->hasUtilizationComparison())
        <div class="flex flex-col gap-2" aria-label="{{ $field->comparisonAriaLabel() }}">
            <p class="cc-weight-percentage-above-bar font-mono text-[11px] font-semibold text-slate-600 dark:text-slate-400">
                {{ $field->utilizationPercentLabel() }}
            </p>
            <progress
                class="cc-weight-progress block h-2 w-full {{ $field->comparisonProgressClass() }}"
                max="100"
                value="{{ $field->progressValue() }}"
                aria-label="{{ $field->utilizationAriaLabel() }}"
                aria-valuetext="{{ $field->utilizationAriaValueText() }}"
            >
                {{ $field->utilizationLabel() }}
            </progress>
            <div class="flex flex-wrap items-start justify-between gap-x-3 gap-y-1 text-[11px] font-medium">
                @if ($field->integratesLimitWithinProgress())
                    <p class="font-mono text-slate-600 dark:text-slate-400">
                        Max limit: {{ $field->limitAmountLabel() }} {{ $field->limitUnit() }}
                    </p>
                @endif
                <p class="{{ $field->comparisonTextClasses() }}">{{ $field->comparisonLabel() }}</p>
            </div>
        </div>
    @elseif ($field->comparisonUnavailableLabel() !== null)
        <p class="text-[11px] font-semibold text-[#4A5568] dark:text-slate-400">
            {{ $field->comparisonUnavailableLabel() }}
        </p>
    @endif

    @if ($field->isDerived())
        <footer class="border-t border-[#1B365D]/10 pt-3 text-[11px] font-medium leading-4 text-[#4A5568] dark:border-slate-700 dark:text-slate-400">
            Derived server-side from confirmed zero-fuel weight and ramp fuel.
        </footer>
    @endif
</article>
