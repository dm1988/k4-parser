@props(['value', 'label', 'accessibleLabel'])

<div {{ $attributes->merge(['class' => 'flex items-end gap-3']) }}>
    <span
        aria-label="{{ $accessibleLabel }}"
        class="font-mono text-5xl font-black leading-none text-[#0B0E14] dark:text-slate-100"
    >
        {{ $value }}
    </span>
    <p class="pb-1 text-sm font-normal leading-5 text-[#4A5568] dark:text-slate-300">
        {{ $label }}
    </p>
</div>
