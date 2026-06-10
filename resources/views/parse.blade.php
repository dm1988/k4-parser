<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Crew Compass</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-[#F8F9FA] text-[#0B0E14]">
    @php
        $result = session('result');
        $parsed = $result['parsed'] ?? null;
        $events = $parsed['calendar_events'] ?? [];
        $trip = $parsed['trip'] ?? [];
        $selectedTypes = old('event_types', $result['filters'] ?? []);
    @endphp

    <header class="border-b border-[#1B365D]/15 bg-[#1B365D] text-[#F8F9FA]">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-5 py-4">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-full border border-[#C5A059] bg-[#0B0E14]/20">
                    <svg aria-hidden="true" viewBox="0 0 40 40" class="h-7 w-7 text-[#C5A059]">
                        <circle cx="20" cy="20" r="15" fill="none" stroke="currentColor" stroke-width="2" />
                        <path d="M24 16 19 29l-3-9-5-4 9-3 4 3Z" fill="currentColor" />
                    </svg>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#C5A059]">Crew Compass</p>
                    <h1 class="text-xl font-bold">Roster Image Parser</h1>
                </div>
            </div>
            <div class="hidden text-right text-sm text-[#F8F9FA]/75 sm:block">
                <p>OCR to duty timeline</p>
                <p class="text-[#C5A059]">Flights • Layovers • Duty</p>
            </div>
        </div>
    </header>

    <main class="mx-auto grid max-w-6xl gap-6 px-5 py-6 @if($result) lg:grid-cols-[minmax(0,1.05fr)_minmax(340px,0.95fr)] @endif">
        <section>
            <div class="mb-5 rounded-lg bg-[#1B365D] p-5 text-[#F8F9FA] shadow-lg shadow-[#1B365D]/10">
                <p class="text-sm font-semibold uppercase tracking-[0.16em] text-[#C5A059]">Flight deck</p>
                <h2 class="mt-2 text-3xl font-bold">K4 Schedule Parsers</h2>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-[#F8F9FA]/80">
                    Upload a roster screenshot and the K4 parser will extract the text, classify the trip details, and return calendar-ready events.
                </p>
            </div>

            <form method="POST" action="{{ route('parse.roster') }}" enctype="multipart/form-data" class="rounded-lg border border-[#1B365D]/15 bg-white shadow-sm">
                @csrf

                <div class="border-b border-[#1B365D]/10 p-5">
                    <div class="flex flex-col gap-4 md:flex-row md:items-end">
                        <div class="flex-1">
                            <label for="image" class="mb-2 block text-sm font-semibold text-[#1B365D]">Roster photo</label>
                            <input
                                id="image"
                                type="file"
                                name="image"
                                accept="image/*"
                                class="block w-full rounded-md border border-[#4A5568]/30 bg-[#F8F9FA] p-3 text-sm text-[#0B0E14] file:mr-4 file:rounded-md file:border-0 file:bg-[#1B365D] file:px-4 file:py-2 file:font-semibold file:text-[#F8F9FA]"
                            >
                            @error('image')
                                <p class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p>
                            @enderror
                        </div>

                        <button class="rounded-md bg-[#C5A059] px-6 py-3 font-bold text-[#0B0E14] shadow-sm transition hover:bg-[#b6914b]">
                            Parse Photo
                        </button>
                    </div>
                </div>

                <fieldset class="p-5">
                    <legend class="text-sm font-semibold text-[#1B365D]">Event filters</legend>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <label class="flex items-center gap-3 rounded-md border border-[#1B365D]/15 bg-[#F8F9FA] px-4 py-3 text-sm font-medium text-[#0B0E14]">
                            <input
                                type="checkbox"
                                name="event_types[]"
                                value="flight"
                                class="h-4 w-4 accent-[#C5A059]"
                                @checked(in_array('flight', $selectedTypes, true))
                            >
                            Flights only
                        </label>
                        <label class="flex items-center gap-3 rounded-md border border-[#1B365D]/15 bg-[#F8F9FA] px-4 py-3 text-sm font-medium text-[#0B0E14]">
                            <input
                                type="checkbox"
                                name="event_types[]"
                                value="layover"
                                class="h-4 w-4 accent-[#C5A059]"
                                @checked(in_array('layover', $selectedTypes, true))
                            >
                            Layovers only
                        </label>
                    </div>
                    <p class="mt-2 text-sm text-[#4A5568]">Leave both unchecked to include duties, flights, and layovers.</p>
                    @error('event_types')
                        <p class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p>
                    @enderror
                    @error('event_types.*')
                        <p class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p>
                    @enderror
                </fieldset>

                <details class="border-t border-[#1B365D]/10 p-5">
                    <summary class="cursor-pointer font-semibold text-[#1B365D]">Paste extracted text instead</summary>
                    <textarea
                        name="text"
                        class="mt-3 h-40 w-full rounded-md border border-[#4A5568]/30 bg-[#F8F9FA] p-3 text-sm text-[#0B0E14] outline-none focus:border-[#C5A059]"
                        placeholder="Paste OCR text if you already have it..."
                    >{{ old('text') }}</textarea>
                    @error('text')
                        <p class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p>
                    @enderror
                    <button class="mt-3 rounded-md bg-[#1B365D] px-5 py-2.5 font-semibold text-[#F8F9FA] transition hover:bg-[#142a49]">
                        Parse Pasted Text
                    </button>
                </details>
            </form>
        </section>

        @if($result)
            <aside class="space-y-5">
                <section class="rounded-lg border border-[#1B365D]/15 bg-white shadow-sm">
                    <div class="border-b border-[#1B365D]/10 bg-[#0B0E14] px-5 py-4 text-[#F8F9FA]">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#C5A059]">Manifest</p>
                        <h2 class="mt-1 text-lg font-bold">Parsed Output</h2>
                    </div>

                    <div class="space-y-4 p-5">
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div class="rounded-md bg-[#F8F9FA] p-3">
                                <p class="text-xs font-semibold uppercase text-[#4A5568]">Source</p>
                                <p class="mt-1 font-bold text-[#1B365D]">{{ ucfirst($result['source'] ?? 'text') }}</p>
                            </div>
                            <div class="rounded-md bg-[#F8F9FA] p-3">
                                <p class="text-xs font-semibold uppercase text-[#4A5568]">Trip</p>
                                <p class="mt-1 font-bold text-[#1B365D]">{{ $trip['trip_number'] ?? 'Pending' }}</p>
                            </div>
                            <div class="rounded-md bg-[#F8F9FA] p-3">
                                <p class="text-xs font-semibold uppercase text-[#4A5568]">Events</p>
                                <p class="mt-1 font-bold text-[#1B365D]">{{ count($events) }}</p>
                            </div>
                        </div>

                        @if($events)
                            <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <p class="text-sm text-[#4A5568]">Download the parsed events as a calendar file.</p>
                                <a
                                    href="{{ route('parse.export', ['event_types' => $result['filters'] ?? []]) }}"
                                    class="inline-flex items-center justify-center rounded-md bg-[#C5A059] px-4 py-2 text-sm font-semibold text-[#0B0E14] transition hover:bg-[#b6914b]"
                                >
                                    Download .ics
                                </a>
                            </div>
                        @endif

                        @if($events)
                            <div class="space-y-3">
                                @foreach($events as $event)
                                    <article class="rounded-md border border-[#1B365D]/10 p-3">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <p class="font-semibold text-[#0B0E14]">{{ $event['title'] }}</p>
                                                <p class="mt-1 text-xs text-[#4A5568]">{{ $event['start'] }} → {{ $event['end'] }}</p>
                                            </div>
                                            <span class="rounded-full bg-[#C5A059]/20 px-2.5 py-1 text-xs font-bold uppercase text-[#1B365D]">
                                                {{ $event['type'] }}
                                            </span>
                                        </div>
                                        <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                            <p class="text-xs text-[#4A5568]">Export this line item as a single .ics event.</p>
                                            <a
                                                href="{{ route('parse.export.event', ['eventIndex' => $loop->index]) }}"
                                                class="inline-flex items-center justify-center rounded-md bg-[#1B365D] px-3 py-2 text-xs font-semibold text-[#F8F9FA] transition hover:bg-[#142a49]"
                                            >
                                                Download event
                                            </a>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        @else
                            <p class="rounded-md bg-[#F8F9FA] p-4 text-sm text-[#4A5568]">No calendar events matched the current filters.</p>
                        @endif
                    </div>
                </section>

                <details class="rounded-lg border border-[#1B365D]/15 bg-white p-5 shadow-sm">
                    <summary class="cursor-pointer font-semibold text-[#1B365D]">Raw JSON</summary>
                    <pre class="mt-4 max-h-[28rem] overflow-auto rounded-md bg-[#0B0E14] p-4 text-xs text-[#C5A059]">{{ json_encode($result, JSON_PRETTY_PRINT) }}</pre>
                </details>
            </aside>
        @endif
    </main>
</body>
</html>
