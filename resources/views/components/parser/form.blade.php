@props(['model', 'file' => null])

<form wire:submit="parseRoster" class="cc-card" id="parserForm">

    <div class="border-b border-[#1B365D]/10 p-5">
        <div class="flex flex-col gap-4 md:flex-row md:items-end">
            <div class="flex-1">
                <label for="file" class="mb-2 block text-sm font-semibold text-[#1B365D]">Roster screenshot or trip
                    PDF</label>
                <input id="file" type="file" name="file" accept="application/pdf,image/*"
                    wire:model="file"
                    class="block w-full rounded-md border border-[#4A5568]/30 bg-[#F8F9FA] p-3 text-sm text-[#0B0E14] file:mr-4 file:rounded-md file:border-0 file:bg-[#1B365D] file:px-4 file:py-2 file:font-semibold file:text-[#F8F9FA]">
                @error('file')
                <p class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p>
                @enderror
            </div>

            <button id="parseBtn" type="submit" data-parse-submit
                wire:loading.attr="disabled"
                wire:target="file, parseRoster"
                class="inline-flex items-center justify-center gap-2 rounded-md bg-[#C5A059] px-6 py-3 font-bold text-[#0B0E14] shadow-sm transition hover:bg-[#b6914b] disabled:cursor-not-allowed disabled:bg-[#1B365D]/55 disabled:text-[#F8F9FA]/80 disabled:shadow-none">
                <span data-submit-label wire:loading.remove wire:target="parseRoster">Extract</span>
                <span data-submit-label wire:loading wire:target="parseRoster">Extracting...</span>
                <span data-submit-spinner wire:loading wire:target="file, parseRoster" class="text-base leading-none" aria-hidden="true">⏳</span>
            </button>
        </div>

        <div id="parserStatus" class="mt-4 rounded-lg border border-[#1B365D]/10 bg-[#1B365D]/[0.03] px-4 py-3"
            role="status" aria-live="polite">
            <div class="flex items-start gap-3">
                <span id="parserStatusDot" class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full bg-emerald-500" aria-hidden="true"></span>
                <div wire:loading.remove wire:target="file, parseRoster" class="space-y-1">
                    @if ($file)
                        <p id="parserStatusTitle" class="text-sm font-semibold text-[#1B365D]">File ready to extract</p>
                        <p id="parserStatusMessage" class="text-sm text-[#4A5568]">{{ $file->getClientOriginalName() }} selected. Click Extract to begin.</p>
                    @else
                        <p id="parserStatusTitle" class="text-sm font-semibold text-[#1B365D]">Ready to extract</p>
                        <p id="parserStatusMessage" class="text-sm text-[#4A5568]">Upload a screenshot or PDF, then extract. Uploads usually finish in under 15 seconds.</p>
                    @endif
                </div>
                <div wire:loading wire:target="file, parseRoster" class="space-y-1">
                    <p class="text-sm font-semibold text-[#1B365D]">Reading and extracting your schedule...</p>
                    <p class="text-sm text-[#4A5568]">Keep this tab open while the schedule is processed.</p>
                </div>
            </div>
        </div>
    </div>

    @php
        $hasFilterSelections = $model->selectedTypes !== [];
        $hasFilterErrors = $errors->has('eventTypes') || $errors->has('eventTypes.*');
    @endphp

    <details class="border-t border-[#1B365D]/10 p-5 group" @open($hasFilterSelections || $hasFilterErrors)>
        <summary
            class="flex cursor-pointer list-none items-center justify-between gap-3 font-semibold text-[#1B365D] [&::-webkit-details-marker]:hidden">
            <span>Filters</span>

            <span class="inline-flex items-center gap-2 text-sm text-[#4A5568]">
                <span class="hidden sm:inline">Show options</span>
                <x-heroicon-o-chevron-down class="h-4 w-4 group-open:hidden" />
                <x-heroicon-o-chevron-up class="hidden h-4 w-4 group-open:block" />
            </span>
        </summary>

        <div class="mt-4">
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($model->filterOptions as $option)
                <label
                    class="flex items-center gap-3 rounded-md border border-[#1B365D]/15 bg-[#F8F9FA] px-4 py-3 text-sm font-medium text-[#0B0E14]">
                    <input type="checkbox" name="event_types[]" value="{{ $option['value'] }}"
                        wire:model="eventTypes"
                        class="h-4 w-4 accent-[#C5A059]">
                    <span>
                        <span class="block">{{ $option['label'] }}</span>
                        <span class="block text-xs font-normal text-[#4A5568]">{{ $option['description'] }}</span>
                    </span>
                </label>
                @endforeach
            </div>

            <p class="mt-2 text-sm text-[#4A5568]">Leave all unchecked to include duties, flights, and layovers.</p>

            @error('eventTypes')
            <p class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p>
            @enderror

            @error('eventTypes.*')
            <p class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p>
            @enderror
        </div>
    </details>

    {{--
        Temporarily hidden fallback text-entry flow.
        Keep this block intact so "Paste extracted text instead" can be restored quickly.

    <details class="border-t border-[#1B365D]/10 p-5">
        <summary class="cursor-pointer font-semibold text-[#1B365D]">Paste extracted text instead</summary>
        <textarea name="text"
            class="mt-3 h-40 w-full rounded-md border border-[#4A5568]/30 bg-[#F8F9FA] p-3 text-sm text-[#0B0E14] outline-none focus:border-[#C5A059]"
            placeholder="Paste OCR text if you already have it...">{{ $model->text }}</textarea>
        @error('text')
        <p class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p>
        @enderror
        <p class="mt-3">
            <button class="cc-btn-cta disabled:cursor-not-allowed disabled:bg-[#1B365D]/55 disabled:text-[#F8F9FA]/80"
                type="submit" data-parse-submit x-bind:disabled="isSubmitting">
                <span data-submit-label x-text="isSubmitting ? 'Extracting...' : 'Extract'">Extract</span>
                <span data-submit-spinner class="hidden" x-bind:class="{ 'hidden': ! isSubmitting }"
                    aria-hidden="true"> ⏳</span>
            </button>
        </p>
    </details>
    --}}
</form>
