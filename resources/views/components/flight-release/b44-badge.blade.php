@props(['label'])

@if ($label !== null)
    <span
        title="OpSpec B44 Authorized"
        {{ $attributes->class('inline-flex shrink-0 items-center rounded-md bg-amber-500/10 px-2 py-1 text-xs font-semibold text-amber-400 ring-1 ring-inset ring-amber-500/20') }}
    >
        {{ $label }}
    </span>
@endif
