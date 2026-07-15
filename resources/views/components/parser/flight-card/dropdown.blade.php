@props([
    'title' => null,
    'icon' => null,
    'open' => false,
    'position' => 'left',
])

<details
    @if($open) open @endif
    {{ $attributes->merge([
        'class' => 'group relative inline-block'
    ]) }}
>
    <summary
        class="inline-flex h-10 cursor-pointer list-none items-center gap-2 rounded-xl border border-[#D8E0EC] bg-white px-3 text-[#1F3C6D] shadow-sm transition hover:bg-[#F8FAFD] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#1B365D]/20 [&::-webkit-details-marker]:hidden"
    >
        @if($icon)
            <x-dynamic-component
                :component="$icon"
                class="h-4 w-4 shrink-0"
            />
        @endif

        @if($title)
            <span class="text-sm font-semibold">
                {{ $title }}
            </span>
        @endif

        <x-heroicon-o-chevron-down
            class="h-3.5 w-3.5 shrink-0 text-[#8090A9] transition group-open:rotate-180"
        />
    </summary>

    <div
        @class([
            'absolute z-30 mt-2 w-72 rounded-2xl border border-[#E1E7F0] bg-white p-4 shadow-xl',
            'left-0' => $position === 'left',
            'right-0' => $position === 'right',
        ])
    >
        {{ $slot }}
    </div>
</details>
