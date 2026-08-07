@props(['id'])

<div {{ $attributes->class('inline-flex items-center gap-2') }}>
    <label for="{{ $id }}" class="sr-only">{{ __('Color theme') }}</label>
    <select
        id="{{ $id }}"
        data-theme-selector
        aria-label="{{ __('Color theme') }}"
        class="rounded-md border-gray-300 bg-white py-1.5 pe-8 ps-3 text-sm font-medium text-gray-700 shadow-sm transition focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:focus:border-indigo-400 dark:focus:ring-indigo-400"
    >
        <option value="light">{{ __('Light') }}</option>
        <option value="dark">{{ __('Dark') }}</option>
        <option value="system">{{ __('System') }}</option>
    </select>
</div>
