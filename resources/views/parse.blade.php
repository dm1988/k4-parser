    @php
        $result = session('result');
        $parsed = $result['parsed'] ?? null;
        $events = $parsed['calendar_events'] ?? [];
        $trip = $parsed['trip'] ?? [];
        $selectedTypes = old('event_types', $result['filters'] ?? []);
    @endphp

    <main class="mx-auto grid max-w-6xl gap-6 px-5 py-6 @if($result) lg:grid-cols-[minmax(0,1.05fr)_minmax(340px,0.95fr)] @endif">
        <section>
            <div class="mb-5 rounded-lg bg-[#1B365D] p-5 text-[#F8F9FA] shadow-lg shadow-[#1B365D]/10">
                <p class="text-sm font-semibold uppercase tracking-[0.16em] text-[#C5A059]">Flight deck</p>
                <h2 class="mt-2 text-3xl font-bold">K4 Schedule Parsers</h2>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-[#F8F9FA]/80">Upload a roster screenshot and the K4 parser will extract the text, classify the trip details, and return calendar-ready events.</p>
            </div>

            <x-parser.form :result="$result" :selectedTypes="$selectedTypes" />
        </section>

        @if($result)
            <x-parser.result :result="$result" :events="$events" :trip="$trip" />
        @endif
    </main>
