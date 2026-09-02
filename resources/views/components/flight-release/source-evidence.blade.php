@props([
    'message' => 'Source evidence remains private to this result and is not included in the Livewire snapshot.',
])

<aside {{ $attributes->merge(['class' => 'rounded-lg border border-[#C5A059]/35 bg-[#C5A059]/10 p-4 text-sm text-[#1B365D] dark:border-[#C5A059]/25 dark:bg-[#C5A059]/10 dark:text-[#E8D2A5]']) }}>
    <div class="flex items-start gap-3">
        <x-heroicon-o-shield-check class="mt-0.5 h-5 w-5 shrink-0" />
        <div class="flex min-w-0 flex-col gap-1">
            <p class="font-bold">Source evidence protected</p>
            <p class="leading-5 opacity-90">{{ $message }}</p>
        </div>
    </div>
</aside>
