<div class="mx-auto max-w-6xl">
    @if (! $available)
        <section class="mx-auto max-w-4xl">
            <section class="rounded-lg border border-amber-200 bg-amber-50 p-5 text-amber-900">
                <p class="text-sm font-semibold uppercase tracking-[0.16em]">Feature unavailable</p>
                <h2 class="mt-2 text-2xl font-bold">Schedule extractor access is currently unavailable.</h2>
                <p class="mt-3 text-sm leading-6">This feature is disabled or restricted for your account.</p>
            </section>
        </section>
    @elseif ($view === 'upload')
        <section wire:key="schedule-extractor-upload" class="mx-auto flex max-w-3xl flex-col gap-6">
            <header class="flex flex-col items-center px-1 text-center">
                <span class="block text-xs font-bold uppercase tracking-widest text-[#C5A059]">
                    Jeppesen Crew Access
                </span>
                <h1 class="mt-2 text-4xl font-bold tracking-tight text-[#1B365D] md:text-5xl">Schedule Extractor</h1>
                <p class="mt-4 max-w-2xl text-base leading-relaxed text-[#4A5568]">
                    Upload a roster screenshot or trip PDF to instantly convert your schedule into calendar-ready events.
                </p>
            </header>

            <x-extract.form :event-types="$eventTypes" :file="$file" :filter-options="$filterOptions" />
        </section>
    @elseif ($view === 'results' && $viewModel?->hasResult())
        <section wire:key="schedule-extractor-results-{{ $parseKey }}" class="mx-auto max-w-4xl space-y-6">
            <div class="flex justify-end">
                <button
                    type="button"
                    wire:click="extractAnotherRoster"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center justify-center rounded-md bg-[#1B365D] px-4 py-2 text-sm font-semibold text-[#F8F9FA] transition hover:bg-[#142a49] disabled:cursor-not-allowed disabled:opacity-60"
                >
                    Extract another roster
                </button>
            </div>

            <x-extract.result :model="$viewModel->result" />
        </section>
    @endif
</div>
