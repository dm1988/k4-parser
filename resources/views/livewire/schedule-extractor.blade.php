<div class="mx-auto max-w-6xl">
    @if (! $available)
        <section class="mx-auto max-w-4xl">
            <section class="rounded-lg border border-amber-200 bg-amber-50 p-5 text-amber-900 dark:border-amber-800 dark:bg-amber-950/50 dark:text-amber-200">
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
                <h1 class="mt-2 text-4xl font-bold tracking-tight text-[#1B365D] dark:text-slate-100 md:text-5xl">Schedule Extractor</h1>
                <p class="mt-4 max-w-2xl text-base leading-relaxed text-[#4A5568] dark:text-slate-400">
                    Upload a roster screenshot or trip PDF to instantly convert your schedule into calendar-ready events.
                </p>
            </header>

            <x-extract.form :event-types="$eventTypes" :files="$files" :filter-options="$filterOptions" />
        </section>
    @elseif ($view === 'results' && $viewModel?->hasResult())
        <section wire:key="schedule-extractor-results-{{ $parseKey }}" class="mx-auto max-w-4xl space-y-6">
            <div class="flex justify-end">
                <button
                    type="button"
                    wire:click="extractAnotherRoster"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center justify-center rounded-md bg-[#1B365D] px-4 py-2 text-sm font-semibold text-[#F8F9FA] transition hover:bg-[#142a49] disabled:cursor-not-allowed disabled:opacity-60 dark:bg-[#C5A059] dark:text-[#0B0E14] dark:hover:bg-[#d3b271]"
                >
                    Extract another roster
                </button>
            </div>

            <x-extract.result :model="$viewModel->result" />
        </section>
    @endif

    @if ($failedFiles !== [])
        <x-modal name="schedule-extraction-errors" max-width="md" focusable>
            <section
                wire:key="schedule-extraction-errors-{{ $parseKey }}"
                role="dialog"
                aria-modal="true"
                aria-labelledby="schedule-extraction-errors-title"
                aria-describedby="schedule-extraction-errors-description"
                class="p-6 sm:p-8"
            >
                <div class="flex flex-col gap-5">
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-amber-100 text-[#B8860B] dark:bg-amber-500/15 dark:text-amber-300" aria-hidden="true">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9.303 3.376c.866 1.5-.217 3.374-1.948 3.374H4.645c-1.73 0-2.813-1.874-1.948-3.374L10.052 3.38c.865-1.5 3.03-1.5 3.896 0l7.355 12.746ZM12 16.5h.008v.008H12V16.5Z" />
                        </svg>
                    </div>

                    <div class="flex flex-col gap-2">
                        <h2 id="schedule-extraction-errors-title" class="text-xl font-semibold text-[#0B0E14] dark:text-slate-100">
                            Some files could not be extracted
                        </h2>
                        <p id="schedule-extraction-errors-description" class="text-sm leading-6 text-[#4A5568] dark:text-slate-300">
                            Results from the other files are ready. Review the files that were skipped before continuing.
                        </p>
                    </div>

                    <ul class="flex max-h-72 flex-col gap-3 overflow-y-auto" aria-label="Files with extraction errors">
                        @foreach ($failedFiles as $failedFile)
                            <li class="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-700/60 dark:bg-amber-950/30">
                                <p class="break-all font-mono text-sm font-semibold text-[#1B365D] dark:text-blue-200">
                                    {{ $failedFile['filename'] }}
                                </p>
                                <p class="mt-1 text-sm leading-5 text-[#4A5568] dark:text-slate-300">
                                    {{ $failedFile['error'] }}
                                </p>
                            </li>
                        @endforeach
                    </ul>

                    <div class="flex justify-end">
                        <button
                            type="button"
                            x-on:click="$dispatch('close')"
                            class="inline-flex items-center justify-center rounded-md bg-[#1B365D] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#142a49] focus:outline-none focus:ring-2 focus:ring-[#C5A059] focus:ring-offset-2 dark:bg-[#C5A059] dark:text-[#0B0E14] dark:hover:bg-[#d3b271] dark:focus:ring-offset-slate-900"
                        >
                            View extracted results
                        </button>
                    </div>
                </div>
            </section>
        </x-modal>
    @endif
</div>
