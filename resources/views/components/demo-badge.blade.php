@props([
    'variant' => 'default',
])

@php
$variants = [
    'default' => 'border border-amber-200 bg-amber-100 text-amber-800 dark:border-amber-700 dark:bg-amber-900/40 dark:text-amber-200',
    'info' => 'border border-blue-200 bg-blue-100 text-blue-800 dark:border-blue-700 dark:bg-blue-900/40 dark:text-blue-200',
    'success' => 'border border-green-200 bg-green-100 text-green-800 dark:border-green-700 dark:bg-green-900/40 dark:text-green-200',
];
@endphp

<span {{ $attributes->class([
    'cc-badge inline-flex shrink-0 items-center rounded px-2 py-0.5 text-xs font-medium',
    $variants[$variant] ?? $variants['default'],
]) }}>
    {{ $slot->isEmpty() ? __('Demo') : $slot }}
</span>
