@php
// Fall back to a blank state if the controller didn't inject one,
// rather than scanning old session payloads manually.
$viewModel ??= \App\View\Models\Parser\ParserPageViewModel::fromSession(null);
@endphp

@if (! $viewModel->available)
<section class="mx-auto max-w-4xl px-5 py-6">
    <section class="rounded-lg border border-amber-200 bg-amber-50 p-5 text-amber-900">
        <p class="text-sm font-semibold uppercase tracking-[0.16em]">Feature unavailable</p>
        <h2 class="mt-2 text-2xl font-bold">Schedule parser access is currently unavailable.</h2>
        <p class="mt-3 text-sm leading-6">This feature is disabled or restricted for your account.</p>
    </section>
</section>
@else

<div
    class="mx-auto grid max-w-6xl gap-6 px-5 py-6 @if($viewModel->hasResult()) lg:grid-cols-[minmax(0,1.05fr)_minmax(340px,0.95fr)] @endif">
    <section>
        <div class="mb-5 rounded-lg bg-[#1B365D] p-5 text-[#F8F9FA] shadow-lg shadow-[#1B365D]/10">
            <p class="text-sm font-semibold uppercase tracking-[0.16em] text-[#C5A059]">Flight deck</p>
            <h2 class="mt-2 text-3xl font-bold">JCA Schedule Parser</h2>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-[#F8F9FA]/80">Upload a roster screenshot or trip PDF and the
                K4 parser will extract the text, classify the trip details, and return calendar-ready events.</p>

            <div class="mt-4 flex items-center gap-x-2 text-sm text-[#F8F9FA]/80">
                <span class="text-[#F8F9FA]/60">Not sure where to start?</span>
                <a class="inline-flex items-center gap-x-1.5 rounded-md bg-[#C5A059]/10 px-3 py-1.5 font-medium text-[#C5A059] border border-[#F8F9FA] transition-all hover:bg-[#C5A059]/20"
                    href="{{ asset('documents/k4-parser-workflow.pdf') }}" target="_blank">
                    <span>View the workflow guide</span>
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                    </svg>
                </a>
            </div>
        </div>

        <x-parser.form :model="$viewModel" />
    </section>

    @if($viewModel->hasResult())
    <x-parser.result :model="$viewModel->result" />
    @endif
</div>
@endif
