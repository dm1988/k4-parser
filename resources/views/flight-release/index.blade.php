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

                    @if (session('flight_route'))
                        <section class="rounded-lg border border-[#1B365D]/10 bg-[#F8F9FA] p-5">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="text-sm font-semibold uppercase tracking-[0.14em] text-[#4A5568]">Extracted route</p>
                                    <p class="mt-1 text-sm text-[#4A5568]">Copy the filed route exactly as extracted from the release.</p>
                                </div>

                                <button
                                    id="copy-flight-route"
                                    type="button"
                                    class="inline-flex items-center justify-center rounded-md bg-[#1B365D] px-4 py-2 text-sm font-semibold text-[#F8F9FA] transition hover:bg-[#142a49]"
                                >
                                    Copy route
                                </button>
                            </div>

                            <textarea
                                id="flight-route-output"
                                readonly
                                rows="4"
                                class="mt-4 block w-full rounded-md border border-[#1B365D]/10 bg-white p-4 font-mono text-sm text-[#0B0E14]"
                            >{{ session('flight_route') }}</textarea>

                            <p id="copy-flight-route-status" class="mt-3 text-sm text-[#4A5568]"></p>
                        </section>

                        <script>
                            const copyFlightRouteButton = document.getElementById('copy-flight-route');
                            const flightRouteOutput = document.getElementById('flight-route-output');
                            const copyFlightRouteStatus = document.getElementById('copy-flight-route-status');

                            if (copyFlightRouteButton && flightRouteOutput && copyFlightRouteStatus) {
                                copyFlightRouteButton.addEventListener('click', async () => {
                                    await navigator.clipboard.writeText(flightRouteOutput.value);
                                    copyFlightRouteStatus.textContent = 'Route copied.';
                                });
                            }
                        </script>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
