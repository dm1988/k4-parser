@props([
    'title',
    'subtitle' => null,
    'open' => false,
    'icon' => null,
    'iconClass' => 'h-4 w-4 text-slate-500',
])

<details
    @if($open) open @endif
    {{ $attributes->merge([
        'class' => 'group rounded-lg border border-slate-200 bg-white shadow-sm'
    ]) }}
>
    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-4 py-3 [&::-webkit-details-marker]:hidden">
        <div class="flex min-w-0 items-start gap-3">
            @if($icon)
                <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-100">
                    <x-dynamic-component
                        :component="$icon"
                        class="{{ $iconClass }}"
                    />
                </span>
            @endif

            <div class="min-w-0">
                <p class="text-sm font-semibold text-slate-900">
                    {{ $title }}
                </p>

                @if($subtitle)
                    <p class="mt-0.5 text-xs text-slate-500">
                        {{ $subtitle }}
                    </p>
                @endif
            </div>
        </div>

        <div class="shrink-0 text-slate-400 transition group-open:rotate-180">
            <x-heroicon-o-chevron-down class="h-4 w-4" />
        </div>
    </summary>

    <div class="border-t border-slate-100 px-4 py-4">
        {{ $slot }}
    </div>
</details>