<div class="p-4 sm:p-6">
    @if (! $isResultsView)
        <form wire:key="flight-plan-brief-upload" wire:submit="extractFlightPlan" class="flex flex-col gap-4">
            <div class="flex flex-col gap-2">
                <label for="flight-release" class="block text-sm font-semibold text-[#1B365D] dark:text-slate-200">
                    Flight release PDF
                </label>
                <input
                    id="flight-release"
                    type="file"
                    wire:model="flightRelease"
                    accept="application/pdf,.pdf"
                    class="cc-file-input"
                >

                <div class="min-h-5">
                    @error('flightRelease')
                        <p class="text-sm font-medium text-red-700 dark:text-red-400">{{ $message }}</p>
                    @enderror

                    <p
                        wire:loading
                        wire:target="flightRelease"
                        role="status"
                        class="text-sm font-medium text-[#4A5568] dark:text-slate-400"
                    >
                        Uploading PDF…
                    </p>

                    @if ($flightRelease && ! $errors->has('flightRelease'))
                        <p wire:loading.remove wire:target="flightRelease" class="text-sm text-[#4A5568] dark:text-slate-400">
                            Ready: {{ $flightRelease->getClientOriginalName() }}
                        </p>
                    @endif
                </div>
            </div>

            <div class="flex justify-end">
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="flightRelease,extractFlightPlan"
                    @disabled($flightRelease === null)
                    class="inline-flex min-w-36 items-center justify-center gap-2 rounded-md bg-[#C5A059] px-5 py-3 text-sm font-semibold text-[#0B0E14] transition hover:bg-[#b6914b] disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <span wire:loading.remove wire:target="extractFlightPlan">Extract route</span>
                    <span wire:loading.inline-flex wire:target="extractFlightPlan" class="items-center gap-2" role="status">
                        <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"></path>
                        </svg>
                        Processing flight plan…
                    </span>
                </button>
            </div>
        </form>
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

            <x-flight-release.plan-card
                :model="$model"
                :departure-airport="$model->departureAirport()"
                :destination-airport="$model->destinationAirport()"
                :alternate-airport="$model->alternateAirport()"
            />
        </section>
    @endif
</div>
