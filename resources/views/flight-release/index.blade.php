<x-app-layout>
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

                <livewire:flight-plan-brief />
            </div>
        </div>
    </div>
</x-app-layout>
