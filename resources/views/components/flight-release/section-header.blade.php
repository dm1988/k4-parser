@props([
    'title',
    'icon',
    'availability',
    'absenceIsGood' => false,
])

<header {{ $attributes->merge(['class' => 'flex flex-wrap items-start justify-between gap-3 border-b border-[#1B365D]/10 bg-[#F8F9FA] px-4 py-4 dark:border-slate-700 dark:bg-slate-800 sm:px-5']) }}>
    <div class="flex min-w-0 items-center gap-3">
        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[#1B365D] text-[#C5A059] dark:bg-slate-950">
            <x-dynamic-component :component="'heroicon-o-'.$icon" class="h-5 w-5" />
        </span>
        <div class="min-w-0">
            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#4A5568] dark:text-slate-400">Task workspace</p>
            <h2 class="truncate text-lg font-bold text-[#1B365D] dark:text-slate-100">{{ $title }}</h2>
        </div>
    </div>

    <x-flight-release.status :availability="$availability" :absence-is-good="$absenceIsGood" />
</header>
