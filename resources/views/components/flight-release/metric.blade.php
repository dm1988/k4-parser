@props([
    'label',
    'value' => null,
    'meta' => null,
    'mono' => true,
])

<div {{ $attributes->merge(['class' => 'min-w-0 rounded-lg border border-[#1B365D]/10 bg-white px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900']) }}>
    <dt class="text-[10px] font-bold uppercase tracking-[0.16em] text-[#4A5568] dark:text-slate-400">
        {{ $label }}
    </dt>
    <dd @class([
        'mt-1 break-words text-sm font-bold text-[#0B0E14] dark:text-slate-100',
        'font-mono' => $mono,
    ])>
        {{ $value ?? 'Not present' }}
    </dd>
    @if ($meta)
        <dd class="mt-0.5 break-words text-[11px] font-medium text-[#4A5568] dark:text-slate-400">
            {{ $meta }}
        </dd>
    @endif
</div>
