@props(['label', 'summary', 'clearAction', 'clearLabel'])

<details wire:ignore.self {{ $attributes->merge(['class' => 'min-w-0 self-start rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900']) }}>
    <summary class="min-h-11 cursor-pointer rounded-xl px-3 py-3 text-sm font-semibold text-[#1B365D] focus-visible:ring-2 focus-visible:ring-amber-500 dark:text-amber-200">
        {{ $label }}
        @if ($summary !== [])<span class="ms-1 rounded-full bg-[#C5A059]/15 px-2 py-0.5 text-xs">{{ count($summary) }} active</span>@endif
    </summary>
    <div class="flex flex-col gap-3 border-t border-slate-200 p-3 text-xs dark:border-slate-700">
        {{ $slot }}
        <button type="button" wire:click="{{ $clearAction }}" wire:loading.attr="disabled" wire:target="{{ $clearAction }}" @disabled($summary === []) class="min-h-11 self-start rounded-lg px-2 font-semibold underline focus-visible:ring-2 focus-visible:ring-amber-500 disabled:opacity-50">
            <span wire:loading.remove wire:target="{{ $clearAction }}">{{ $clearLabel }}</span><span wire:loading wire:target="{{ $clearAction }}" role="status">Clearing…</span>
        </button>
    </div>
</details>
