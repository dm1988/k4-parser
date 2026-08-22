@props(['model'])

<div {{ $attributes->merge(['class' => 'flex min-w-0 flex-col gap-5 p-3 sm:p-5']) }}>
    <section aria-labelledby="flight-init-fields-heading" class="flex min-w-0 flex-col gap-3">
        <div class="flex flex-wrap items-end justify-between gap-2">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#4A5568] dark:text-slate-400">ACARS reference</p>
                <h3 id="flight-init-fields-heading" class="text-base font-bold text-[#1B365D] dark:text-slate-100">Initialization fields</h3>
            </div>
            <p class="text-xs font-semibold text-[#4A5568] dark:text-slate-400">Confirmed values only</p>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($model->flightInitFields() as $field)
                @if ($field['value'] !== null && $field['value'] !== '')
                    <x-flight-release.copy-field
                        :id="$field['id']"
                        :label="$field['label']"
                        :value="$field['value']"
                        class="rounded-lg border border-[#1B365D]/10 bg-white p-3 dark:border-slate-700 dark:bg-slate-900"
                    />
                @else
                    <dl>
                        <x-flight-release.metric :label="$field['label']" empty-text="Not confirmed" />
                    </dl>
                @endif
            @endforeach
        </div>

        <p class="text-xs leading-5 text-[#4A5568] dark:text-slate-400">ACARS INIT DATE is repeated only from the explicit Takeoff and Landing Report field; it is not derived from the release flight date.</p>
    </section>

    <section aria-labelledby="flight-init-crew-heading" class="flex min-w-0 flex-col gap-3">
        <div>
            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#4A5568] dark:text-slate-400">Confirmed crew</p>
            <h3 id="flight-init-crew-heading" class="text-base font-bold text-[#1B365D] dark:text-slate-100">Crew list</h3>
        </div>

        @if ($model->flightInitCrewMembers() === [])
            <p class="rounded-lg border border-dashed border-[#1B365D]/15 bg-[#F8F9FA] p-4 text-sm font-medium text-[#4A5568] dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-400">No confirmed crew list was found in the supported release section.</p>
        @else
            <ul class="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($model->flightInitCrewMembers() as $member)
                    <li class="flex min-w-0 flex-col gap-2 rounded-lg border border-[#1B365D]/10 bg-white p-3 dark:border-slate-700 dark:bg-slate-900">
                        <span class="block break-words text-sm font-bold text-[#0B0E14] dark:text-slate-100">{{ $member['name'] }}</span>
                        @if ($member['details'])
                            <span class="block font-mono text-xs text-[#4A5568] dark:text-slate-400">{{ $member['details'] }}</span>
                        @endif

                        <div class="flex items-center gap-2 border-t border-[#1B365D]/10 pt-2 dark:border-slate-700">
                            <span class="text-[10px] font-bold uppercase tracking-[0.14em] text-[#4A5568] dark:text-slate-400">Employee number</span>
                            @if ($member['employeeNumber'])
                                <span id="flight-init-crew-employee-{{ $loop->iteration }}" class="font-mono text-xs font-bold text-[#0B0E14] dark:text-slate-100">{{ $member['employeeNumber'] }}</span>
                                <x-flight-release.copy-button
                                    :target="'flight-init-crew-employee-'.$loop->iteration"
                                    :label="$member['name'].' employee number'"
                                    :status="'flight-init-crew-employee-'.$loop->iteration.'-status'"
                                    :compact="true"
                                />
                                <span id="flight-init-crew-employee-{{ $loop->iteration }}-status" role="status" aria-live="polite" class="sr-only"></span>
                            @else
                                <span class="font-mono text-xs font-semibold text-[#4A5568] dark:text-slate-400">Not confirmed</span>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <x-flight-release.source-evidence message="Flight Init source fragments remain private to this extraction result and are not included in the Livewire snapshot." />
</div>
