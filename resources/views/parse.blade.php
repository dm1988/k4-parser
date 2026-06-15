@php
    $viewModel ??= \App\View\Models\Parser\ParserPageViewModel::fromSession(
        session('result'),
        session()->getOldInput(),
    );
@endphp

    <main class="mx-auto grid max-w-6xl gap-6 px-5 py-6 @if($viewModel->hasResult()) lg:grid-cols-[minmax(0,1.05fr)_minmax(340px,0.95fr)] @endif">
        <section>
            <div class="mb-5 rounded-lg bg-[#1B365D] p-5 text-[#F8F9FA] shadow-lg shadow-[#1B365D]/10">
                <p class="text-sm font-semibold uppercase tracking-[0.16em] text-[#C5A059]">Flight deck</p>
                <h2 class="mt-2 text-3xl font-bold">K4 Schedule Parsers</h2>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-[#F8F9FA]/80">Upload a roster screenshot or trip PDF and the K4 parser will extract the text, classify the trip details, and return calendar-ready events.</p>
            </div>

            <x-parser.form :model="$viewModel" />
        </section>

        @if($viewModel->hasResult())
            <x-parser.result :model="$viewModel->result" />
        @endif
    </main>
