@props([
    'items',
    'idPrefix' => 'maintenance-item',
])

<ol {{ $attributes->merge(['class' => 'flex min-w-0 flex-col gap-3']) }}>
    @foreach ($items as $item)
        @php($itemId = $idPrefix.'-'.$loop->iteration)
        <li wire:key="{{ $itemId }}">
            <article class="overflow-hidden rounded-xl border border-[#1B365D]/10 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <header class="flex flex-wrap items-start justify-between gap-3 border-b border-[#1B365D]/10 bg-[#F8F9FA] px-4 py-3 dark:border-slate-700 dark:bg-slate-800">
                    <div class="flex min-w-0 items-center gap-2">
                        <span
                            class="rounded-full px-2.5 py-1 text-[10px] font-bold tracking-[0.12em] {{ $item['typeBadgeColor'] }}"
                            title="{{ $item['typeTitle'] }} — {{ $item['typeDescription'] }}"
                        >{{ $item['type'] }}</span>
                        <h4 id="{{ $itemId }}" class="break-all font-mono text-sm font-bold text-[#1B365D] dark:text-slate-100">{{ $item['number'] }}</h4>
                        @if ($item['copyable'])
                            <x-flight-release.copy-button
                                :target="$itemId"
                                :label="$item['type'].' '.$item['number'].' number'"
                                :status="$itemId.'-status'"
                                :compact="true"
                            />
                            <span
                                id="{{ $itemId }}-status"
                                role="status"
                                aria-live="polite"
                                class="sr-only"
                            ></span>
                        @endif
                    </div>
                    @if ($item['status'])
                        <span class="rounded-full bg-slate-200 px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.12em] text-slate-700 dark:bg-slate-700 dark:text-slate-200">{{ $item['status'] }}</span>
                    @endif
                </header>

                <div class="flex min-w-0 flex-col gap-4 p-4">
                    <div class="flex min-w-0 flex-col gap-1">
                        <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-[#4A5568] dark:text-slate-400">Description</p>
                        <p class="break-words text-sm leading-6 text-[#0B0E14] dark:text-slate-100">{{ $item['description'] }}</p>
                    </div>

                    @if ($item['reference'])
                        <div class="flex min-w-0 flex-col gap-1">
                            <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-[#4A5568] dark:text-slate-400">DMI / reference</p>
                            <p class="break-all font-mono text-sm font-bold text-[#1B365D] dark:text-sky-300">{{ $item['reference'] }}</p>
                        </div>
                    @endif

                    @if ($item['limitations'] || $item['procedures'])
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            @if ($item['limitations'])
                                <div class="flex min-w-0 flex-col gap-1 rounded-lg border border-amber-300/60 bg-amber-50 p-3 dark:border-amber-400/20 dark:bg-amber-400/10">
                                    <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-amber-800 dark:text-amber-200">Source limitation</p>
                                    <p class="break-words text-sm leading-5 text-amber-950 dark:text-amber-100">{{ $item['limitations'] }}</p>
                                </div>
                            @endif
                            @if ($item['procedures'])
                                <div class="flex min-w-0 flex-col gap-1 rounded-lg border border-[#1B365D]/10 bg-[#F8F9FA] p-3 dark:border-slate-700 dark:bg-slate-800">
                                    <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-[#4A5568] dark:text-slate-400">Source procedure</p>
                                    <p class="break-words text-sm leading-5 text-[#0B0E14] dark:text-slate-100">{{ $item['procedures'] }}</p>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </article>
        </li>
    @endforeach
</ol>
