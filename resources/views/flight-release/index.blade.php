<x-app-layout>
    <div class="py-6 sm:py-8">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-lg bg-white shadow-sm dark:bg-slate-900 dark:shadow-black/20">
                <div class="border-b border-[#1B365D]/10 bg-[#1B365D] px-4 py-5 text-[#F8F9FA] dark:border-slate-600 dark:bg-[#1B365D] sm:px-6">
                    <p class="text-sm font-semibold uppercase tracking-[0.16em] text-[#C5A059]">Flight deck</p>
                    <h1 class="mt-2 text-3xl font-bold">Flight Plan Extractor</h1>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-[#F8F9FA]/80">
                        Upload a flight release PDF and extract the filed route block for quick copying.
                    </p>
                </div>

                <div class="space-y-6 p-4 sm:p-6">
                    <form method="POST" action="{{ route('flight-release.store') }}" enctype="multipart/form-data" class="space-y-4">
                        @csrf

                        <div>
                            <label for="flight_release" class="mb-2 block text-sm font-semibold text-[#1B365D] dark:text-slate-200">
                                Flight release PDF
                            </label>
                            <input
                                id="flight_release"
                                type="file"
                                name="flight_release"
                                accept="application/pdf,.pdf"
                                class="cc-file-input"
                            >
                            @error('flight_release')
                                <p class="mt-2 text-sm font-medium text-red-700 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex justify-end">
                            <button
                                type="submit"
                                class="inline-flex items-center justify-center rounded-md bg-[#C5A059] px-5 py-3 text-sm font-semibold text-[#0B0E14] transition hover:bg-[#b6914b]"
                            >
                                Extract route
                            </button>
                        </div>
                    </form>

                    @if ($model->hasFlightPlan())
                        <x-flight-release.plan-card
                            :model="$model"
                            :departure-airport="$model->departureAirport()"
                            :destination-airport="$model->destinationAirport()"
                            :alternate-airport="$model->alternateAirport()"
                        />
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
