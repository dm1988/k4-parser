@props(['model'])

<div {{ $attributes->merge(['class' => 'flex min-w-0 flex-col gap-5 p-3 sm:p-5']) }}>
    <section aria-labelledby="envelope-flight-context-heading" class="flex min-w-0 flex-col gap-3">
        <div class="flex flex-wrap items-end justify-between gap-2">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#4A5568] dark:text-slate-400">Shared release context</p>
                <h3 id="envelope-flight-context-heading" class="text-base font-bold text-[#1B365D] dark:text-slate-100">Flight details</h3>
            </div>
            <p class="text-xs font-semibold text-[#4A5568] dark:text-slate-400">Confirmed fields only</p>
        </div>

        <dl class="grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-4">
            <x-flight-release.metric label="Trip number" :value="$model->tripNumber()" empty-text="Not confirmed" />
            <x-flight-release.metric label="Flight number" :value="$model->flightNumber()" empty-text="Not confirmed" />
            <x-flight-release.metric label="Aircraft type" :value="$model->aircraftType()" empty-text="Not confirmed" />
            <x-flight-release.metric label="Tail number" :value="$model->tailNumber()" empty-text="Not confirmed" />
            <x-flight-release.metric label="Departure" :value="$model->departure()" empty-text="Not confirmed" />
            <x-flight-release.metric label="Destination" :value="$model->destination()" empty-text="Not confirmed" />
        </dl>
    </section>

    <section aria-labelledby="envelope-crew-heading" class="flex min-w-0 flex-col gap-3">
        <div>
            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#4A5568] dark:text-slate-400">Shared confirmed section</p>
            <h3 id="envelope-crew-heading" class="text-base font-bold text-[#1B365D] dark:text-slate-100">Crew list</h3>
        </div>
        @if ($model->crewMembers() === [])
            <p class="rounded-lg border border-dashed border-[#1B365D]/15 bg-[#F8F9FA] p-4 text-sm font-medium text-[#4A5568] dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-400">No confirmed crew list was found in the supported release section.</p>
        @else
            <ul class="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($model->crewMembers() as $member)
                    <li class="min-w-0 rounded-lg border border-[#1B365D]/10 bg-white p-3 dark:border-slate-700 dark:bg-slate-900">
                        <span class="block break-words text-sm font-bold text-[#0B0E14] dark:text-slate-100">{{ $member['name'] }}</span>
                        @if ($member['details'])
                            <span class="block font-mono text-xs text-[#4A5568] dark:text-slate-400">{{ $member['details'] }}</span>
                        @endif
                        @if ($member['highMins'])
                            <span class="mt-2 inline-flex w-fit items-center gap-1 rounded-full bg-amber-100 px-2 py-1 text-[10px] font-bold uppercase tracking-[0.12em] text-amber-900 dark:bg-amber-400/15 dark:text-amber-200">
                                <x-heroicon-o-exclamation-triangle class="h-3.5 w-3.5" />
                                High mins
                            </span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <x-flight-release.source-evidence message="Envelope source fragments remain private to this extraction result and are not included in the Livewire snapshot." />
</div>
