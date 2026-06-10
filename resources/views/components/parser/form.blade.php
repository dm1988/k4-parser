<form method="POST" action="{{ route('parse.roster') }}" enctype="multipart/form-data" class="cc-card">
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
        <legend class="text-sm font-semibold text-[#1B365D]">Filters</legend>
        <div class="mt-3 grid gap-3 sm:grid-cols-2">
            <label class="flex items-center gap-3 rounded-md border border-[#1B365D]/15 bg-[#F8F9FA] px-4 py-3 text-sm font-medium text-[#0B0E14]">
                <input
                    type="checkbox"
                    name="event_types[]"
                    value="flight"
                    class="h-4 w-4 accent-[#C5A059]"
                    @checked(in_array('flight', old('event_types', $result['filters'] ?? []), true))
                >
                Flights only
            </label>
            <label class="flex items-center gap-3 rounded-md border border-[#1B365D]/15 bg-[#F8F9FA] px-4 py-3 text-sm font-medium text-[#0B0E14]">
                <input
                    type="checkbox"
                    name="event_types[]"
                    value="layover"
                    class="h-4 w-4 accent-[#C5A059]"
                    @checked(in_array('layover', old('event_types', $result['filters'] ?? []), true))
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
        <button class="mt-3 cc-btn-cta">
            Parse Pasted Text
        </button>
    </details>
</form>
