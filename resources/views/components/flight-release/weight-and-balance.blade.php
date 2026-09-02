@props(['model'])

<div class="flex flex-col gap-4 p-4 sm:p-5">
    <div class="rounded-lg border border-[#C5A059]/35 bg-[#C5A059]/10 p-4 text-sm text-[#1B365D] dark:border-[#C5A059]/25 dark:bg-[#C5A059]/10 dark:text-[#E8D2A5]">
        <div class="flex items-start gap-3">
            <x-heroicon-o-scale class="mt-0.5 h-5 w-5 shrink-0" />
            <div class="flex min-w-0 flex-col gap-1">
                <p class="font-bold">Planned source values</p>
                <p class="leading-5 opacity-90">This task displays planned source values only. Actual weights are not implemented.</p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
        @foreach ($model->weightBalanceGroups() as $group)
            <section class="flex min-w-0 flex-col gap-3 rounded-xl border border-[#1B365D]/10 bg-[#F8F9FA] p-3 dark:border-slate-700 dark:bg-slate-800/60">
                <header class="flex min-h-16 flex-col gap-1 border-b border-[#1B365D]/10 px-1 pb-3 dark:border-slate-700">
                    <h3 class="text-xs font-black uppercase tracking-[0.16em] text-[#1B365D] dark:text-[#C5A059]">{{ $group['label'] }}</h3>
                    <p class="text-[11px] font-medium leading-4 text-[#4A5568] dark:text-slate-400">{{ $group['description'] }}</p>
                </header>

                <div class="flex flex-1 flex-col gap-3">
                    @foreach ($group['fields'] as $field)
                        <x-flight-release.weight-balance-field :field="$field" class="flex-1" />
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>

    <x-flight-release.source-evidence message="Weight and balance source fragments remain private. Ramp weight is derived server-side only after confirmed zero-fuel weight and ramp fuel are available in the same unit." />
</div>
