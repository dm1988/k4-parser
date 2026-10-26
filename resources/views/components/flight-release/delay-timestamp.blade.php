@props(['field', 'label'])

<div class="flex flex-col gap-1" wire:key="delay-field-{{ $field }}">
    <label for="delay-{{ $field }}" class="text-sm font-semibold text-[#1B365D] dark:text-slate-200">{{ $label }} (UTC)</label>
    <div class="flex items-center gap-2">
        <input id="delay-{{ $field }}" type="datetime-local" step="60" data-delay-input="{{ $field }}" wire:model.live.debounce.500ms="inputs.{{ $field }}" aria-describedby="delay-{{ $field }}-error" class="min-w-0 flex-1 rounded-lg border-slate-300 bg-white font-mono text-sm text-[#0B0E14] focus:border-[#C5A059] focus:ring-[#C5A059] dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:[color-scheme:dark]" />
        <button type="button" wire:click="setNow('{{ $field }}')" wire:loading.attr="disabled" aria-label="Set {{ strtolower($label) }} to now in UTC" class="rounded-lg border border-[#1B365D]/20 px-3 py-2 text-sm font-semibold text-[#1B365D] focus-visible:ring-2 focus-visible:ring-[#C5A059] disabled:opacity-50 dark:border-slate-500 dark:text-slate-100">Now</button>
    </div>
    <p id="delay-{{ $field }}-error" class="text-sm text-red-700 dark:text-red-300" role="status">@error('inputs.'.$field) {{ $message }} @enderror</p>
</div>
