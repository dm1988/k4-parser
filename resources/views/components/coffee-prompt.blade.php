@props(['show' => false])

<x-modal name="buy-me-a-coffee" :show="$show" max-width="md" focusable>
    <section
        role="dialog"
        aria-modal="true"
        aria-labelledby="buy-me-a-coffee-title"
        aria-describedby="buy-me-a-coffee-description"
        class="p-6 sm:p-8"
    >
        <div class="flex flex-col gap-5">
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-amber-100 text-2xl dark:bg-amber-500/15" aria-hidden="true">
                ☕
            </div>

            <div class="flex flex-col gap-2">
                <h2 id="buy-me-a-coffee-title" class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                    Enjoying Crew Compass?
                </h2>
                <p id="buy-me-a-coffee-description" class="text-sm leading-6 text-gray-600 dark:text-gray-300">
                    If Crew Compass has made your day a little easier, you can support its continued development with a coffee. No pressure, everything keeps working either way.
                </p>
            </div>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <x-secondary-button type="button" x-on:click="$dispatch('close')">
                    Maybe later
                </x-secondary-button>
                <a
                    href="{{ config('services.buy_me_a_coffee.url') }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center justify-center rounded-md bg-amber-500 px-4 py-2 text-sm font-semibold text-gray-950 transition hover:bg-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900"
                >
                    Buy me a coffee
                </a>
            </div>
        </div>
    </section>
</x-modal>
