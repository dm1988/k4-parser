<div class="p-4 sm:p-6">
    @if (! $isResultsView)
        <div wire:key="flight-plan-brief-upload" class="mx-auto flex max-w-2xl flex-col gap-4">
            <div>
                <label
                    for="flight-release"
                    wire:loading.class="cursor-wait border-[#C5A059]/70"
                    wire:target="flightRelease"
                    class="group relative flex min-h-48 cursor-pointer flex-col items-center justify-center overflow-hidden rounded-3xl border-2 border-dashed border-[#1B365D]/20 bg-white px-6 py-6 text-center transition duration-300 hover:border-[#C5A059]/70 hover:bg-white hover:shadow-lg focus-within:border-[#C5A059] focus-within:ring-4 focus-within:ring-[#C5A059]/20 dark:border-slate-600 dark:bg-slate-800/80 dark:shadow-lg dark:shadow-black/20 dark:hover:border-[#C5A059]/70 dark:hover:bg-slate-800"
                >
                    <input
                        id="flight-release"
                        type="file"
                        wire:model="flightRelease"
                        wire:loading.attr="disabled"
                        wire:target="flightRelease"
                        accept="application/pdf,.pdf"
                        class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                    >

                    <span wire:loading.remove wire:target="flightRelease" class="flex flex-col items-center">
                        <span class="mb-5 inline-flex rounded-2xl bg-[#1B365D] p-4 text-[#F8F9FA] shadow-md transition duration-300 group-hover:bg-[#C5A059] group-hover:text-[#0B0E14]" aria-hidden="true">
                            <svg class="h-9 w-9" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 0 1-.88-7.903A5 5 0 1 1 15.9 6H16a5 5 0 0 1 1 9.9M15 13l-3-3m0 0-3 3m3-3v12" />
                            </svg>
                        </span>

                        <span class="max-w-full text-xl font-bold text-[#1B365D] dark:text-slate-100">
                            Drop your flight plan here
                        </span>

                        <span class="mt-2 max-w-md text-sm leading-6 text-[#4A5568] dark:text-slate-400">
                            Upload one PDF flight plan. Click to browse your files.
                        </span>
                    </span>

                    <span wire:loading.flex wire:target="flightRelease" class="hidden flex-col items-center gap-4" role="status">
                        <svg class="h-10 w-10 animate-spin text-[#C5A059]" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4Zm2 5.291A7.962 7.962 0 0 1 4 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647Z" />
                        </svg>
                        <span class="text-xl font-bold text-[#1B365D] dark:text-slate-100">Processing flight plan…</span>
                        <span class="text-sm text-[#4A5568] dark:text-slate-400">Please wait while your PDF is uploaded and parsed.</span>
                    </span>
                </label>

                <div class="mt-2 min-h-5 text-center" aria-live="polite">
                    @error('flightRelease')
                        <p class="text-sm font-medium text-red-700 dark:text-red-400">{{ $message }}</p>
                    @enderror

                </div>
            </div>
        </div>
    @else
        <section wire:key="flight-plan-brief-results" class="flex flex-col gap-6">
            <div class="flex justify-end">
                <button
                    type="button"
                    wire:click="extractAnotherFlightPlan"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center justify-center rounded-md bg-[#1B365D] px-4 py-2 text-sm font-semibold text-[#F8F9FA] transition hover:bg-[#142a49] disabled:cursor-not-allowed disabled:opacity-60 dark:bg-[#C5A059] dark:text-[#0B0E14] dark:hover:bg-[#d3b271]"
                >
                    Extract another flight plan
                </button>
            </div>

            <x-flight-release.workspace
                :tasks="$tasks"
                :active-task="$activeTaskCase"
                :model="$model"
            />
        </section>
    @endif
</div>
