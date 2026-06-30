<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-[#C5A059]">Operations</p>
                <h1 class="text-2xl font-semibold text-[#0B0E14]">Fleet timetable</h1>
            </div>
            <div class="text-right text-sm text-gray-600">
                <div class="font-semibold text-[#1B365D]">All times UTC</div>
                <div>{{ $viewModel->windowStart->format('M j, H:i') }} – {{ $viewModel->windowEnd->format('M j, H:i') }}</div>
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-[1800px] px-4 py-8 sm:px-6 lg:px-8">
        <div class="mb-4 flex flex-wrap items-center gap-x-5 gap-y-2 text-xs font-medium text-gray-600">
            @foreach ($viewModel->statusLegend as $status => $classes)
                <span class="inline-flex items-center gap-1.5">
                    <span class="h-2.5 w-2.5 rounded-sm {{ $classes }}"></span>{{ $status }}
                </span>
            @endforeach
            <span class="ml-auto">Now: {{ $viewModel->now->format('M j, H:i') }} UTC</span>
        </div>

        <div class="overflow-x-auto rounded-xl border border-slate-300 bg-white shadow-sm">
            <div class="min-w-[1500px]">
                <div class="grid grid-cols-[11rem_1fr] border-b border-slate-300 bg-[#0B0E14] text-white">
                    <div class="flex h-16 items-end border-r border-white/15 px-4 pb-2 text-xs font-bold uppercase tracking-wider">Aircraft</div>
                    <div class="relative h-16" aria-hidden="true">
                        @foreach ($viewModel->ticks as $tick)
                            @if ($loop->first || $loop->last || $loop->iteration % 2 === 1)
                                <span class="absolute bottom-2 -translate-x-1/2 whitespace-nowrap text-[10px] font-medium text-slate-300"
                                    style="left: {{ $tick['left'] }}%">{{ $tick['label'] }}</span>
                            @endif
                        @endforeach
                    </div>
                </div>

                @forelse ($viewModel->rows as $row)
                    <div class="grid grid-cols-[11rem_1fr] border-b border-slate-200 last:border-b-0">
                        <div class="flex min-h-20 flex-col justify-center border-r border-slate-300 bg-slate-50 px-4">
                            <a href="{{ $row['flight_aware_url'] }}" target="_blank" rel="noopener noreferrer"
                                class="font-bold text-[#1B365D] underline decoration-[#C5A059] decoration-2 underline-offset-4 hover:text-blue-700"
                                aria-label="View {{ $row['tail_number'] }} on FlightAware">
                                {{ $row['tail_number'] }} ↗
                            </a>
                            <span class="mt-1 truncate text-xs text-gray-500">{{ $row['description'] }}</span>
                        </div>
                        <div class="relative min-h-20 overflow-hidden bg-white"
                            style="background-image: repeating-linear-gradient(to right, transparent 0, transparent calc(3.333333% - 1px), rgb(226 232 240) calc(3.333333% - 1px), rgb(226 232 240) 3.333333%);">
                            <div class="pointer-events-none absolute inset-y-0 z-30 w-px bg-rose-500" style="left: 50%" title="Current time"></div>

                            @foreach ($row['events'] as $event)
                                <div class="absolute top-2 bottom-2 z-20 overflow-hidden rounded-md border px-2 py-1 text-white shadow-sm {{ $event['status_classes'] }}"
                                    style="left: {{ $event['left'] }}%; width: {{ $event['width'] }}%"
                                    title="{{ $event['flight_number'] }} · {{ $event['route'] }} · {{ $event['time_label'] }} · {{ $event['status'] }}">
                                    <div class="truncate text-xs font-bold">{{ $event['route'] }}</div>
                                    <div class="truncate text-[10px] text-white/90">{{ $event['flight_number'] }} · {{ $event['status'] }}</div>
                                    <div class="truncate text-[10px] text-white/80">{{ $event['time_label'] }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <div class="p-12 text-center text-sm text-gray-500">No active aircraft are available.</div>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
