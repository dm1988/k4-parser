@props(['field'])

<article class="flex min-w-0 flex-col gap-3 rounded-lg border border-[#1B365D]/10 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
    <div class="flex items-start justify-between gap-3">
        <h3 class="text-xs font-bold uppercase tracking-[0.14em] text-[#1B365D] dark:text-slate-200">
            {{ $field->label }}
        </h3>
        @if ($field->showsSourceStatusBadge())
            <span class="inline-flex shrink-0 rounded-full px-2 py-1 text-[9px] font-bold uppercase tracking-[0.12em] {{ $field->sourceStatus->badgeClasses() }}">
                {{ $field->sourceStatus->label() }}
            </span>
        @endif
    </div>

    <div class="flex-1 {{ $field->valueLayoutClasses() }}">
        <div class="min-w-0">
            <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-[#4A5568] dark:text-slate-400">Planned</p>
            <p class="mt-1 flex items-baseline gap-1.5 font-mono text-[#0B0E14] dark:text-slate-100">
                <span class="text-xl font-black tracking-tight">{{ $field->plannedAmountLabel() }}</span>
                @if ($field->plannedUnit() !== null)
                    <span class="text-[10px] font-bold tracking-[0.12em] text-[#4A5568] dark:text-slate-400">{{ $field->plannedUnit() }}</span>
                @endif
            </p>
        </div>

        @if ($field->showsStandaloneLimit())
            <div class="min-w-0 text-right">
                <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-[#4A5568] dark:text-slate-400">Structural limit</p>
                <p class="mt-1 flex items-baseline justify-end gap-1.5 font-mono text-[#0B0E14] dark:text-slate-100">
                    <span class="text-xl font-black tracking-tight">{{ $field->limitAmountLabel() }}</span>
                    @if ($field->limitUnit() !== null)
                        <span class="text-[10px] font-bold tracking-[0.12em] text-[#4A5568] dark:text-slate-400">{{ $field->limitUnit() }}</span>
                    @endif
                </p>
            </div>
        @endif
    </div>

    @if ($field->hasUtilizationComparison())
        <div class="flex flex-col gap-1.5" aria-label="{{ $field->comparisonAriaLabel() }}">
            @if ($field->integratesLimitWithinProgress())
                <div class="relative h-3.5 overflow-hidden rounded-full">
                    <progress
                        class="cc-weight-progress h-3.5 w-full {{ $field->comparisonProgressClass() }}"
                        max="100"
                        value="{{ $field->progressValue() }}"
                        aria-label="{{ $field->utilizationAriaLabel() }}"
                        aria-valuetext="{{ $field->utilizationAriaValueText() }}"
                    >
                        {{ $field->utilizationLabel() }}
                    </progress>
                    <div class="pointer-events-none absolute inset-0 flex items-center px-1.5 pt-0.5 font-mono text-[8px] font-extrabold leading-none tracking-wide" aria-hidden="true">
                        <span class="truncate rounded-full px-1.5 text-white">{{ $field->progressOverlayLabel() }}</span>
                    </div>
                </div>
            @else
                <div class="flex items-center justify-between gap-3 text-[10px] font-semibold {{ $field->comparisonTextClasses() }}">
                    <span>{{ $field->utilizationLabel() }}</span>
                    <span>{{ $field->comparisonLabel() }}</span>
                </div>
                <progress
                    class="cc-weight-progress h-2 w-full {{ $field->comparisonProgressClass() }}"
                    max="100"
                    value="{{ $field->progressValue() }}"
                    aria-label="{{ $field->utilizationAriaLabel() }}"
                    aria-valuetext="{{ $field->utilizationLabel() }}"
                >
                    {{ $field->utilizationLabel() }}
                </progress>
            @endif
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
