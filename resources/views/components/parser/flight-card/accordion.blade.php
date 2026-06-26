@props([
    'title',
    'subtitle' => null,
    'open' => false,
    'icon' => null,
    'iconClass' => 'h-4 w-4 text-slate-500',
])
<span class="w-full space-y-2">
<details
    @if($open) open @endif
    {{ $attributes->merge([
        'class' => 'group rounded-lg border border-slate-200 bg-white shadow-sm'
    ]) }}
>
    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-3 py-2 [&::-webkit-details-marker]:hidden">
        <div class="flex min-w-0 items-center gap-2.5">
            @if($icon)
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slate-100">
                    <x-dynamic-component
                        :component="$icon"
                        class="{{ $iconClass }}"
                    />
                </span>
            @endif

            <div class="min-w-0 leading-none">
                <p class="text-sm font-semibold text-slate-900">
                    {{ $title }}
                </p>

                @if($subtitle)
                    <p class="mt-0.5 text-[11px] text-slate-500">
                        {{ $subtitle }}
                    </p>
                @endif
            </div>
        </div>

        <div class="shrink-0 text-slate-400 transition group-open:rotate-180">
            <x-heroicon-o-chevron-down class="h-4 w-4" />
        </div>
    </summary>

    <div class="border-t border-slate-100 px-3 py-3">
        {{ $slot }}
    </div>
</details>
</span>