@props(['value', 'label', 'accessibleLabel', 'unit' => null])

@if ($unit === null)
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
@else
    <div {{ $attributes->merge(['class' => 'flex min-w-0 flex-col gap-1']) }}>
        <span aria-label="{{ $accessibleLabel }}" class="flex max-w-full flex-wrap items-baseline gap-x-2 gap-y-1 font-mono text-4xl font-black leading-none text-[#0B0E14] dark:text-slate-100 sm:text-5xl">
            <span aria-hidden="true">{{ $value }}</span>
            <span aria-hidden="true" class="font-sans text-base font-medium text-[#4A5568] dark:text-slate-300">{{ $unit }}</span>
        </span>
        @if ($label !== null)
            <p class="text-sm font-normal leading-5 text-[#4A5568] dark:text-slate-300">{{ $label }}</p>
        @endif
    </div>
@endif
