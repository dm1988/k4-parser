@props([
    'count',
    'label',
    'noun' => 'item',
    'tone' => 'warning',
])

<span
    {{ $attributes->class([
        'inline-flex min-w-5 shrink-0 items-center justify-center',
        'rounded-full bg-emerald-100 px-1.5 py-0.5 font-mono text-[10px] font-bold tabular-nums text-emerald-900 dark:bg-emerald-400/15 dark:text-emerald-200' => $tone === 'success',
        'rounded-full bg-amber-100 px-1.5 py-0.5 font-mono text-[10px] font-bold tabular-nums text-amber-900 dark:bg-amber-400/15 dark:text-amber-200' => $tone !== 'success',
    ]) }}
    aria-label="{{ $label }}: {{ $count }} {{ \Illuminate\Support\Str::plural($noun, $count) }}"
>{{ $count }}</span>
