<div class="flex flex-col gap-5 p-3 sm:p-5" x-data="flightDelayForm">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h3 class="text-lg font-bold text-[#1B365D] dark:text-slate-100">Record a flight delay</h3>
            <p class="text-sm text-[#4A5568] dark:text-slate-300">All dates and times are UTC. Changes save after you pause typing.</p>
        </div>
        @if ($savedAt)
            <a href="{{ route('flight-delays.index') }}" wire:navigate class="rounded text-sm font-semibold text-[#1B365D] underline underline-offset-4 focus-visible:ring-2 focus-visible:ring-[#C5A059] dark:text-slate-200">Delay history</a>
        @endif
    </div>

    <dl class="grid grid-cols-2 gap-3 rounded-lg bg-[#F8F9FA] p-3 dark:bg-slate-800 sm:grid-cols-3">
        @foreach ($flightDetails as $label => $value)
            <div>
                <dt class="text-xs font-semibold text-[#4A5568] dark:text-slate-400">{{ $label }}</dt>
                <dd class="break-words font-mono text-sm text-[#0B0E14] dark:text-slate-100">{{ $value }}</dd>
            </div>
        @endforeach
    </dl>

    <form wire:submit="savePending" x-on:input.capture="inspectInput($event)" x-on:submit.capture="validateClient($event)" class="flex flex-col gap-5">
        <fieldset class="grid gap-4 sm:grid-cols-2">
            <legend class="mb-3 text-sm font-bold text-[#1B365D] dark:text-slate-100">Event timeline (UTC)</legend>
            @foreach ($events as $field => $label)
                <x-flight-release.delay-timestamp :field="$field" :label="$label" />
            @endforeach
        </fieldset>

        <fieldset class="grid gap-4 sm:grid-cols-2">
            <legend class="mb-3 text-sm font-bold text-[#1B365D] dark:text-slate-100">Delay duration (UTC)</legend>
            @foreach ($durationFields as $field => $label)
                <x-flight-release.delay-timestamp :field="$field" :label="$label" />
            @endforeach
            <p class="text-xs text-[#4A5568] dark:text-slate-400 sm:col-span-2">Duration uses only the entered delay start/end interval. Missing times are not inferred from events or scheduled departure.</p>
        </fieldset>

        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ($textFields as $field => $label)
                <div class="flex flex-col gap-1" wire:key="delay-field-{{ $field }}">
                    <label for="delay-{{ $field }}" class="text-sm font-semibold text-[#1B365D] dark:text-slate-200">{{ $label }}</label>
                    <textarea id="delay-{{ $field }}" data-delay-input="{{ $field }}" wire:model.live.debounce.500ms="inputs.{{ $field }}" rows="3" maxlength="{{ $field === 'other_label' ? 160 : 10000 }}" aria-describedby="delay-{{ $field }}-error" class="w-full rounded-lg border-slate-300 bg-white text-sm text-[#0B0E14] focus:border-[#C5A059] focus:ring-[#C5A059] dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"></textarea>
                    <p id="delay-{{ $field }}-error" class="text-sm text-red-700 dark:text-red-300" role="status">@error('inputs.'.$field) {{ $message }} @enderror</p>
                </div>
            @endforeach
        </div>

        <div class="flex flex-col gap-2" aria-live="polite">
            <p x-show="clientError" x-text="clientError" class="text-sm font-semibold text-red-700 dark:text-red-300"></p>
            @error('save')
                <p class="text-sm font-semibold text-red-700 dark:text-red-300">{{ $message }}</p>
            @enderror
            <p wire:loading role="status" class="text-sm text-[#4A5568] dark:text-slate-300">Saving…</p>
            <p wire:loading.remove class="text-sm text-[#4A5568] dark:text-slate-300">
                <span x-show="dirty">Unsaved changes</span>
                <span x-show="!dirty">@if ($savedAt) Saved at {{ $savedAt }} UTC @else No observations recorded yet @endif</span>
            </p>
        </div>

        <div class="flex flex-wrap gap-3">
            <button type="submit" wire:loading.attr="disabled" x-bind:disabled="busy" class="rounded-lg border border-[#1B365D]/25 px-4 py-2 text-sm font-semibold text-[#1B365D] focus-visible:ring-2 focus-visible:ring-[#C5A059] disabled:opacity-50 dark:border-slate-500 dark:text-slate-100">Save / retry</button>
            <button type="button" x-on:click="generateReport" x-bind:disabled="busy" wire:loading.attr="disabled" class="rounded-lg bg-[#1B365D] px-4 py-2 text-sm font-semibold text-white focus-visible:ring-2 focus-visible:ring-[#C5A059] focus-visible:ring-offset-2 disabled:opacity-50 dark:bg-[#C5A059] dark:text-[#0B0E14]">Generate report text</button>
        </div>
        <p class="text-xs text-[#4A5568] dark:text-slate-400">All populated notes are included. This creates report text for you to copy. Wait for a saved confirmation before closing; unsent changes are not available offline.</p>
    </form>

    @if ($reportText !== null)
        <div x-show="!dirty && !clientError && !busy">
            <x-flight-release.delay-report :text="$reportText" />
        </div>
    @endif
</div>
