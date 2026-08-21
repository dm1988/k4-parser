@props([
    'title',
    'message',
    'icon' => 'information-circle',
])

<div {{ $attributes->merge(['class' => 'flex min-h-64 flex-col items-center justify-center gap-3 px-5 py-10 text-center']) }}>
    <span class="flex h-12 w-12 items-center justify-center rounded-full bg-[#1B365D]/8 text-[#1B365D] dark:bg-slate-800 dark:text-[#C5A059]">
        <x-dynamic-component :component="'heroicon-o-'.$icon" class="h-6 w-6" />
    </span>
    <div class="flex max-w-lg flex-col gap-1">
        <h3 class="text-base font-bold text-[#1B365D] dark:text-slate-100">{{ $title }}</h3>
        <p class="text-sm leading-6 text-[#4A5568] dark:text-slate-400">{{ $message }}</p>
    </div>
</div>
