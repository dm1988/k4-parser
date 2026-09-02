<aside {{ $attributes->merge(['class' => 'rounded-lg border border-amber-300/70 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-400/25 dark:bg-amber-400/10 dark:text-amber-100']) }}>
    <div class="flex items-start gap-3">
        <x-heroicon-o-exclamation-triangle class="h-5 w-5 shrink-0" />
        <div class="flex min-w-0 flex-col gap-1">
            <p class="font-bold">No airworthiness determination</p>
            <p class="leading-5">This view repeats source-listed maintenance information and does not determine dispatchability. Review the controlling maintenance record and procedures.</p>
        </div>
    </div>
</aside>
