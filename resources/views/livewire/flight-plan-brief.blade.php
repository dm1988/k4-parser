<div class="py-6 sm:py-8">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="overflow-hidden rounded-lg bg-white shadow-sm dark:bg-slate-900 dark:shadow-black/20">
            <div class="border-b border-[#1B365D]/10 bg-[#1B365D] px-4 py-5 text-[#F8F9FA] dark:border-slate-600 dark:bg-[#1B365D] sm:px-6">
                <p class="text-sm font-semibold uppercase tracking-[0.16em] text-[#C5A059]">Flight deck</p>
                <h1 class="mt-2 text-3xl font-bold">Flight Plan Brief</h1>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-[#F8F9FA]/80">
                    Your flight release, distilled into the details that matter.
                </p>
            </div>

            <div class="p-4 sm:p-6">
    @if (! $isResultsView)
        <div
            wire:key="flight-plan-brief-upload"
            x-data="{ uploadProgress: 0 }"
            x-on:livewire-upload-start="uploadProgress = 0; $refs.uploadStatus.textContent = 'Confirming upload…'; $refs.processingStatus.textContent = 'Upload sent. Waiting for confirmation…'"
            x-on:livewire-upload-progress="uploadProgress = $event.detail.progress"
            x-on:livewire-upload-error="uploadProgress = 0"
            x-on:livewire-upload-cancel="uploadProgress = 0"
            class="mx-auto flex max-w-2xl flex-col gap-4"
        >
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

                    <div wire:loading.remove.flex wire:target="flightRelease" class="flex flex-col items-center gap-2">
                        <span class="mb-3 inline-flex rounded-2xl bg-[#1B365D] p-4 text-[#F8F9FA] shadow-md transition duration-300 group-hover:bg-[#C5A059] group-hover:text-[#0B0E14]" aria-hidden="true">
                            <svg class="h-9 w-9" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 0 1-.88-7.903A5 5 0 1 1 15.9 6H16a5 5 0 0 1 1 9.9M15 13l-3-3m0 0-3 3m3-3v12" />
                            </svg>
                        </span>

                        <span class="block max-w-full text-xl font-bold text-[#1B365D] dark:text-slate-100">
                            Drop your flight plan here
                        </span>

                        <span class="block max-w-md text-sm leading-6 text-[#4A5568] dark:text-slate-400">
                            Upload one PDF flight plan. Click to browse your files. Maximum size: 25 MB.
                        </span>
                    </div>

                    <span wire:loading.flex wire:target="flightRelease" class="hidden w-full max-w-md flex-col items-center gap-4" role="status" aria-live="polite" aria-atomic="true">
                        <svg class="h-10 w-10 animate-spin text-[#C5A059]" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4Zm2 5.291A7.962 7.962 0 0 1 4 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647Z" />
                        </svg>
                        <span x-show="uploadProgress < 100" class="flex w-full flex-col items-center gap-3">
                            <span class="text-xl font-bold text-[#1B365D] dark:text-slate-100" x-text="`Uploading flight plan… ${uploadProgress}%`">Uploading flight plan…</span>
                            <progress class="h-2 w-full accent-[#C5A059]" max="100" x-bind:value="uploadProgress" aria-label="Flight plan upload progress"></progress>
                        </span>
                        <span x-show="uploadProgress >= 100" x-cloak class="flex flex-col items-center gap-2">
                            <span x-ref="uploadStatus" wire:stream.replace="flight-plan-upload-status" class="text-sm font-semibold text-[#1B365D] dark:text-slate-100">Confirming upload…</span>
                            <span x-ref="processingStatus" wire:stream.replace="flight-plan-progress" class="text-sm text-[#4A5568] dark:text-slate-400">Upload sent. Waiting for confirmation…</span>
                            <span class="text-xs text-[#4A5568] dark:text-slate-400">Large documents and scanned pages may take longer. Keep this page open.</span>
                        </span>
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
            @if ($extractionJustCompleted)
                <p class="text-sm font-medium text-[#1B365D] dark:text-slate-100" role="status">Flight plan brief ready. Upload and extraction completed successfully.</p>
            @endif
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
                :fuel-calculator-url="$fuelCalculatorUrl"
            />
        </section>
            @endif
            </div>
        </div>
    </div>
</div>
