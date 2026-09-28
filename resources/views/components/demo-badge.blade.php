@props([
    'variant' => 'default',
])

@php
$variants = [
    'default' => 'border border-amber-200 bg-amber-100 text-amber-800 dark:border-amber-700 dark:bg-amber-900/40 dark:text-amber-200',
    'info' => 'border border-blue-200 bg-blue-100 text-blue-800 dark:border-blue-700 dark:bg-blue-900/40 dark:text-blue-200',
    'success' => 'border border-green-200 bg-green-100 text-green-800 dark:border-green-700 dark:bg-green-900/40 dark:text-green-200',
    'prominent' => 'border border-[#C5A059] bg-[#C5A059] text-[#0B0E14] dark:border-[#E8D2A5] dark:bg-[#E8D2A5] dark:text-[#0B0E14]',
];
@endphp

<span {{ $attributes->class([
    'inline-flex shrink-0 items-center rounded',
    'px-3 py-1 text-sm font-bold uppercase tracking-wide' => $variant === 'prominent',
    'cc-badge rounded px-2 py-0.5 text-xs font-medium' => $variant !== 'prominent',
    $variants[$variant] ?? $variants['default'],
]) }}>
    {{ $slot->isEmpty() ? __('Demo') : $slot }}
</span>
