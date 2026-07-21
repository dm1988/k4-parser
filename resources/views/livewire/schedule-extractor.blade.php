<div class="mx-auto max-w-6xl px-5 py-6">
    @if (! $viewModel->available)
        <section class="mx-auto max-w-4xl">
            <section class="rounded-lg border border-amber-200 bg-amber-50 p-5 text-amber-900">
                <p class="text-sm font-semibold uppercase tracking-[0.16em]">Feature unavailable</p>
                <h2 class="mt-2 text-2xl font-bold">Schedule extractor access is currently unavailable.</h2>
                <p class="mt-3 text-sm leading-6">This feature is disabled or restricted for your account.</p>
            </section>
        </section>
    @elseif ($view === 'upload')
        <section class="mx-auto max-w-3xl">
            <div class="mb-5 rounded-lg bg-[#1B365D] p-5 text-[#F8F9FA] shadow-lg shadow-[#1B365D]/10">
                <header>
                    <span class="block text-xs font-bold uppercase tracking-widest text-[#C5A059]">
                        Jeppesen Crew Access
                    </span>
                    <h1 class="mt-2 text-4xl font-black tracking-tight md:text-5xl">Schedule Extractor</h1>
                    <p class="mt-4 max-w-md text-base leading-relaxed text-[#F8F9FA]/80">
                        Upload a roster screenshot or trip PDF to instantly convert your schedule into calendar-ready events.
                    </p>
                </header>

                <div class="mt-4 flex items-center gap-x-2 text-sm text-[#F8F9FA]/80">
                    <span class="text-[#F8F9FA]/60">Not sure where to start?</span>
                    <a
                        class="inline-flex items-center gap-x-1.5 rounded-md border border-[#F8F9FA] bg-[#C5A059]/10 px-3 py-1.5 font-medium text-[#C5A059] transition-all hover:bg-[#C5A059]/20"
                        href="{{ asset('documents/k4-parser-workflow.pdf') }}"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <span>View the workflow guide</span>
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                        </svg>
                    </a>
                </div>
            </div>

            <x-parser.form :model="$viewModel" :file="$file" />
        </section>
    @elseif ($viewModel->hasResult())
        <section class="mx-auto max-w-4xl space-y-5">
            <div class="flex justify-end">
                <button
                    type="button"
                    wire:click="extractAnotherRoster"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center justify-center rounded-md bg-[#1B365D] px-4 py-2 text-sm font-semibold text-[#F8F9FA] transition hover:bg-[#142a49] disabled:cursor-not-allowed disabled:opacity-60"
                >
                    Extract another roster
                </button>
            </div>

            <x-parser.result :model="$viewModel->result" />
        </section>
    @endif
</div>
