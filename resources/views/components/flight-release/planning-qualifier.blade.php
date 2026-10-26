@props(['show' => false, 'label' => 'Preliminary'])

@if ($show)
    <span {{ $attributes->merge(['class' => 'inline-flex w-fit rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold tracking-normal text-amber-950 dark:bg-amber-400/15 dark:text-amber-100']) }}>{{ $label }}</span>
@endif
