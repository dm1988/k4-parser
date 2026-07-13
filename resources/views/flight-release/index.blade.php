<x-app-layout>
    <div class="py-12">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                <div class="border-b border-[#1B365D]/10 bg-[#1B365D] px-6 py-5 text-[#F8F9FA]">
                    <p class="text-sm font-semibold uppercase tracking-[0.16em] text-[#C5A059]">Flight deck</p>
                    <h1 class="mt-2 text-3xl font-bold">Flight Release Route Extractor</h1>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-[#F8F9FA]/80">
                        Upload a flight release PDF and extract the filed route block for quick copying.
                    </p>
                </div>

                <div class="space-y-6 p-6">
                    <form method="POST" action="{{ route('flight-release.store') }}" enctype="multipart/form-data" class="space-y-4">
                        @csrf

                        <div>
                            <label for="flight_release" class="mb-2 block text-sm font-semibold text-[#1B365D]">
                                Flight release PDF
                            </label>
                            <input
                                id="flight_release"
                                type="file"
                                name="flight_release"
                                accept="application/pdf,.pdf"
                                class="block w-full rounded-md border border-[#4A5568]/30 bg-[#F8F9FA] p-3 text-sm text-[#0B0E14] file:mr-4 file:rounded-md file:border-0 file:bg-[#1B365D] file:px-4 file:py-2 file:font-semibold file:text-[#F8F9FA]"
                            >
                            @error('flight_release')
                                <p class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p>
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
                        @php($departureAirport = $model->departureAirport())
                        @php($destinationAirport = $model->destinationAirport())
                        @php($alternateAirport = $model->alternateAirport())
                        <x-flight-release.plan-card
                            :model="$model"
                            :departure-airport="$departureAirport"
                            :destination-airport="$destinationAirport"
                            :alternate-airport="$alternateAirport"
                        />

                        <script>
                            const copyFlightPlanButtons = document.querySelectorAll('[data-copy-target]');
                            const copyStatusTimeouts = new Map();

                            const showCopyStatus = (status, message) => {
                                const existingTimeouts = copyStatusTimeouts.get(status.id);

                                if (existingTimeouts) {
                                    window.clearTimeout(existingTimeouts.fadeTimeout);
                                    window.clearTimeout(existingTimeouts.clearTimeout);
                                }

                                status.textContent = message;
                                status.classList.remove('opacity-0');
                                status.classList.add('opacity-100');

                                const fadeTimeout = window.setTimeout(() => {
                                    status.classList.remove('opacity-100');
                                    status.classList.add('opacity-0');
                                }, 50);

                                const clearTimeout = window.setTimeout(() => {
                                    status.textContent = '';
                                }, 3050);

                                copyStatusTimeouts.set(status.id, {fadeTimeout, clearTimeout});
                            };

                            copyFlightPlanButtons.forEach((button) => {
                                button.addEventListener('click', async () => {
                                    const output = document.getElementById(button.dataset.copyTarget);
                                    const status = document.getElementById(button.dataset.copyStatus);

                                    if (! output || ! status) {
                                        return;
                                    }

                                    const text = output.value ?? output.textContent?.trim() ?? '';
                                    const label = button.dataset.copyLabel ?? 'Value';

                                    if (text === '') {
                                        showCopyStatus(status, `Unable to copy ${label.toLowerCase()}.`);

                                        return;
                                    }

                                    try {
                                        await navigator.clipboard.writeText(text);
                                        showCopyStatus(status, `${label} copied.`);
                                    } catch (error) {
                                        showCopyStatus(status, `Unable to copy ${label.toLowerCase()}.`);
                                    }
                                });
                            });
                        </script>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
