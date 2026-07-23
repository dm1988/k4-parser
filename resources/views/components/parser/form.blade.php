@props(['eventTypes', 'file' => null, 'filterOptions'])

<form wire:submit="parseRoster" id="parserForm">

    <div class="px-0 pb-7">
        <div class="mx-auto flex max-w-2xl flex-col gap-5">
            <div>
                <label
                    for="file"
                    class="group relative flex min-h-48 cursor-pointer flex-col items-center justify-center overflow-hidden rounded-3xl border-2 border-dashed border-[#1B365D]/20 bg-white px-6 py-6 text-center transition duration-300 hover:border-[#C5A059]/70 hover:bg-white hover:shadow-lg focus-within:border-[#C5A059] focus-within:ring-4 focus-within:ring-[#C5A059]/20"
                >
                    <input
                        id="file"
                        type="file"
                        name="file"
                        accept="application/pdf,image/*"
                        wire:model="file"
                        class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                    >

                    <span class="mb-5 inline-flex rounded-2xl bg-[#1B365D] p-4 text-[#F8F9FA] shadow-md transition duration-300 group-hover:bg-[#C5A059] group-hover:text-[#0B0E14]" aria-hidden="true">
                        <svg class="h-9 w-9" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 0 1-.88-7.903A5 5 0 1 1 15.9 6H16a5 5 0 0 1 1 9.9M15 13l-3-3m0 0-3 3m3-3v12" />
                        </svg>
                    </span>

                    <span class="max-w-full text-xl font-bold text-[#1B365D]">
                        {{ $file ? $file->getClientOriginalName() : 'Drop your schedule here' }}
                    </span>

                    <span class="mt-2 max-w-md text-sm leading-6 text-[#4A5568]">
                        @if ($file)
                            {{ \Illuminate\Support\Number::fileSize($file->getSize()) }} <span aria-hidden="true">&bull;</span> Click to change
                        @else
                            Supports PDF and all image formats. Click to browse your files.
                        @endif
                    </span>
                </label>

                @error('file')
                <p class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p>
                @enderror
            </div>

            <button
                id="parseBtn"
                type="submit"
                data-parse-submit
                @disabled(! $file)
                wire:loading.attr="disabled"
                wire:target="file, parseRoster"
                class="inline-flex w-full items-center justify-center gap-3 rounded-2xl bg-[#C5A059] px-6 py-4 text-lg font-bold text-[#0B0E14] shadow-lg shadow-[#C5A059]/20 transition duration-300 hover:bg-[#D4AF37] hover:shadow-[#C5A059]/40 disabled:cursor-not-allowed disabled:bg-[#1B365D]/10 disabled:text-[#1B365D]/40 disabled:shadow-none"
            >
                <span data-submit-label wire:loading.remove wire:target="parseRoster">Extract Schedule</span>
                <svg wire:loading.remove wire:target="parseRoster" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0-7 7m7-7H3" />
                </svg>
                <span data-submit-label wire:loading wire:target="parseRoster">Extracting Schedule...</span>
                <svg data-submit-spinner wire:loading wire:target="file, parseRoster" class="h-5 w-5 animate-spin" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4Zm2 5.291A7.962 7.962 0 0 1 4 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647Z" />
                </svg>
            </button>

            <div class="flex items-center justify-center gap-2 text-center text-xs font-medium text-[#4A5568]" role="status" aria-live="polite">
                <span class="h-2 w-2 shrink-0 rounded-full bg-emerald-500" aria-hidden="true"></span>
                <span wire:loading.remove wire:target="file, parseRoster">
                    {{ $file ? 'File ready to extract' : 'System online: Ready to process' }}
                </span>
                <span wire:loading wire:target="file, parseRoster">
                    Reading your schedule...
                </span>
            </div>
        </div>
    </div>

    @php
        $hasFilterSelections = $eventTypes !== [];
        $hasFilterErrors = $errors->has('eventTypes') || $errors->has('eventTypes.*');
    @endphp

    <details class="group mx-auto max-w-2xl border-t border-[#1B365D]/10 py-5" @open($hasFilterSelections || $hasFilterErrors)>
        <summary
            class="inline-flex cursor-pointer list-none items-center gap-1.5 font-semibold text-[#1B365D] [&::-webkit-details-marker]:hidden">
            <span>Filters</span>
            <x-heroicon-o-chevron-down class="h-4 w-4 text-[#4A5568] group-open:hidden" />
            <x-heroicon-o-chevron-up class="hidden h-4 w-4 text-[#4A5568] group-open:block" />
        </summary>

        <div class="mt-4">
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($filterOptions as $option)
                <label
                    class="flex items-center gap-3 rounded-md border border-[#1B365D]/15 bg-[#F8F9FA] px-4 py-3 text-sm font-medium text-[#0B0E14]">
                    <input type="checkbox" name="event_types[]" value="{{ $option->value }}"
                        wire:model="eventTypes"
                        class="h-4 w-4 accent-[#C5A059]">
                    <span>
                        <span class="block">{{ $option->filterLabel() }}</span>
                        <span class="block text-xs font-normal text-[#4A5568]">{{ $option->description() }}</span>
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

    <div class="flex flex-col items-center justify-center gap-1 pt-2 text-center text-sm text-[#4A5568] sm:flex-row sm:gap-2">
        <span>Not sure where to start?</span>
        <a
            class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 font-semibold text-[#1B365D] underline decoration-[#C5A059] decoration-2 underline-offset-4 transition hover:text-[#C5A059]"
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

    {{--
        Temporarily hidden fallback text-entry flow.
        Keep this block intact so "Paste extracted text instead" can be restored quickly.

    <details class="border-t border-[#1B365D]/10 p-5">
        <summary class="cursor-pointer font-semibold text-[#1B365D]">Paste extracted text instead</summary>
        <textarea name="text"
            class="mt-3 h-40 w-full rounded-md border border-[#4A5568]/30 bg-[#F8F9FA] p-3 text-sm text-[#0B0E14] outline-none focus:border-[#C5A059]"
            wire:model="text"
            placeholder="Paste OCR text if you already have it..."></textarea>
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
