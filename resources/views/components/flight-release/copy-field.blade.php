@props([
    'id',
    'label',
    'value',
])

<div {{ $attributes->merge(['class' => 'min-w-0']) }}>
    <label for="{{ $id }}" class="block text-[10px] font-bold uppercase tracking-[0.14em] text-[#4A5568] dark:text-slate-400">
        {{ $label }}
    </label>

    <div class="mt-1 flex items-center gap-2">
        <input
            id="{{ $id }}"
            type="text"
            readonly
            value="{{ $value }}"
            class="min-w-0 flex-1 rounded-md border border-[#1B365D]/10 bg-[#F8F9FA] px-2.5 py-2 font-mono text-xs font-semibold text-[#0B0E14] dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
        >

        <x-flight-release.copy-button
            :target="$id"
            :label="$label"
            :status="$id.'-status'"
            :compact="true"
        />
    </div>

    <p
        id="{{ $id }}-status"
        role="status"
        aria-live="polite"
        class="min-h-4 text-[11px] text-[#4A5568] transition-opacity duration-[3000ms] dark:text-slate-400"
    ></p>
</div>
