@props([
    'target',
    'label',
    'status',
    'text' => null,
    'compact' => false,
])

<button
    type="button"
    data-copy-target="{{ $target }}"
    data-copy-label="{{ $label }}"
    data-copy-status="{{ $status }}"
    @class([
        'inline-flex items-center justify-center rounded-md transition',
        'h-8 w-8 shrink-0 border border-[#1B365D]/10 bg-[#F8F9FA] text-[#1B365D] hover:bg-[#eef0f3]' => $compact,
        'gap-2 border border-[#1B365D]/10 bg-white px-3 py-1.5 text-xs font-semibold text-[#1B365D] hover:bg-[#eef0f3]' => ! $compact,
    ])
>
    <x-heroicon-o-document-duplicate @class([
        'h-4 w-4',
    ]) />

    @if ($text)
        <span>{{ $text }}</span>
    @else
        <span class="sr-only">Copy {{ strtolower($label) }}</span>
    @endif
</button>
