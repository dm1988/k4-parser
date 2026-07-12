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

                    @if (session('flight_plan'))
                        @php($flightPlan = session('flight_plan'))
                        <section class="rounded-lg border border-[#1B365D]/10 bg-[#F8F9FA] p-5">
                            <div>
                                <div>
                                    <p class="text-sm font-semibold uppercase tracking-[0.14em] text-[#4A5568]">Extracted flight plan</p>
                                    <p class="mt-1 text-sm text-[#4A5568]">Review the parsed identifiers and copy the route or airports directly.</p>
                                </div>
                            </div>

                            <div class="mt-4 grid gap-4 md:grid-cols-3">
                                <div class="rounded-md border border-[#1B365D]/10 bg-white p-4">
                                    <div class="flex items-center justify-between gap-4">
                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-[#4A5568]">Departure</p>
                                            <p id="departure-output" class="mt-2 font-mono text-lg text-[#0B0E14]">{{ $flightPlan['departure'] }}</p>
                                            <p
                                                id="departure-status"
                                                role="status"
                                                aria-live="polite"
                                                class="mt-2 min-h-5 text-sm text-[#4A5568] transition-opacity duration-[3000ms]"
                                            ></p>
                                        </div>
                                        <button
                                            type="button"
                                            data-copy-target="departure-output"
                                            data-copy-label="Departure"
                                            data-copy-status="departure-status"
                                            class="inline-flex h-10 w-10 items-center justify-center rounded-md bg-[#1B365D] text-[#F8F9FA] transition hover:bg-[#142a49]"
                                        >
                                            <x-heroicon-o-document-duplicate class="h-5 w-5" />
                                            <span class="sr-only">Copy departure</span>
                                        </button>
                                    </div>
                                </div>

                                <div class="rounded-md border border-[#1B365D]/10 bg-white p-4">
                                    <div class="flex items-center justify-between gap-4">
                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-[#4A5568]">Destination</p>
                                            <p id="destination-output" class="mt-2 font-mono text-lg text-[#0B0E14]">{{ $flightPlan['destination'] }}</p>
                                            <p
                                                id="destination-status"
                                                role="status"
                                                aria-live="polite"
                                                class="mt-2 min-h-5 text-sm text-[#4A5568] transition-opacity duration-[3000ms]"
                                            ></p>
                                        </div>
                                        <button
                                            type="button"
                                            data-copy-target="destination-output"
                                            data-copy-label="Destination"
                                            data-copy-status="destination-status"
                                            class="inline-flex h-10 w-10 items-center justify-center rounded-md bg-[#1B365D] text-[#F8F9FA] transition hover:bg-[#142a49]"
                                        >
                                            <x-heroicon-o-document-duplicate class="h-5 w-5" />
                                            <span class="sr-only">Copy destination</span>
                                        </button>
                                    </div>
                                </div>

                                <div class="rounded-md border border-[#1B365D]/10 bg-white p-4">
                                    <div class="flex items-center justify-between gap-4">
                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-[#4A5568]">Alternate</p>
                                            <p id="alternate-output" class="mt-2 font-mono text-lg text-[#0B0E14]">{{ $flightPlan['alternate'] ?? 'None listed' }}</p>
                                            <p
                                                id="alternate-status"
                                                role="status"
                                                aria-live="polite"
                                                class="mt-2 min-h-5 text-sm text-[#4A5568] transition-opacity duration-[3000ms]"
                                            ></p>
                                        </div>
                                        @if ($flightPlan['alternate'])
                                            <button
                                                type="button"
                                                data-copy-target="alternate-output"
                                                data-copy-label="Alternate"
                                                data-copy-status="alternate-status"
                                                class="inline-flex h-10 w-10 items-center justify-center rounded-md bg-[#1B365D] text-[#F8F9FA] transition hover:bg-[#142a49]"
                                            >
                                                <x-heroicon-o-document-duplicate class="h-5 w-5" />
                                                <span class="sr-only">Copy alternate</span>
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                <div class="rounded-md border border-[#1B365D]/10 bg-white p-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-[#4A5568]">Initial altitude</p>
                                    <p class="mt-2 font-mono text-lg text-[#0B0E14]">{{ $flightPlan['initial_altitude'] }}</p>
                                </div>

                                <div class="rounded-md border border-[#1B365D]/10 bg-white p-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-[#4A5568]">Duration</p>
                                    <p class="mt-2 font-mono text-lg text-[#0B0E14]">{{ $flightPlan['duration'] }}</p>
                                </div>
                            </div>

                            <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-start">
                                <div class="sm:flex-1">
                                    <textarea
                                        id="flight-route-output"
                                        readonly
                                        rows="4"
                                        class="block w-full rounded-md border border-[#1B365D]/10 bg-white p-4 font-mono text-sm text-[#0B0E14]"
                                    >{{ $flightPlan['route'] }}</textarea>

                                    <p
                                        id="route-status"
                                        role="status"
                                        aria-live="polite"
                                        class="mt-2 min-h-5 text-sm text-[#4A5568] transition-opacity duration-[3000ms]"
                                    ></p>
                                </div>

                                <button
                                    type="button"
                                    data-copy-target="flight-route-output"
                                    data-copy-label="Route"
                                    data-copy-status="route-status"
                                    class="inline-flex items-center justify-center gap-2 rounded-md bg-[#1B365D] px-4 py-3 text-sm font-semibold text-[#F8F9FA] transition hover:bg-[#142a49] sm:self-stretch"
                                >
                                    <x-heroicon-o-document-duplicate class="h-5 w-5" />
                                    <span>Copy route</span>
                                </button>
                            </div>
                        </section>

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
