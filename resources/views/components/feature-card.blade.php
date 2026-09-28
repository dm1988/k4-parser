@props(['id', 'title', 'description', 'action', 'demo' => false, 'primary' => false])

<article {{ $attributes->class([
    'cc-card relative flex min-w-0 flex-col overflow-hidden',
    'transition hover:border-[#C5A059] hover:shadow-md focus-within:ring-2 focus-within:ring-[#1B365D] focus-within:ring-offset-4 dark:focus-within:ring-[#C5A059] dark:focus-within:ring-offset-slate-950' => $action['url'] !== null,
]) }} aria-labelledby="{{ $id }}-title">
    <div class="cc-card-header flex flex-wrap items-center gap-3">
        @isset($icon)
            <span class="text-[#E8D2A5]" aria-hidden="true">{{ $icon }}</span>
        @endisset
        <h2 id="{{ $id }}-title" class="text-xl font-bold">{{ $title }}</h2>
        @if ($demo)
            <x-demo-badge variant="prominent" data-demo-badge>Demo · Preview</x-demo-badge>
        @endif
    </div>
    <div class="flex flex-1 flex-col gap-6 p-6 sm:p-8">
        <p class="text-base leading-relaxed text-[#4A5568] dark:text-slate-300">{{ $description }}</p>
        <div class="text-sm leading-relaxed text-[#4A5568] dark:text-slate-300">
            {{ $slot }}
        </div>
        <div class="mt-auto border-t border-[#1B365D]/15 pt-6 dark:border-slate-700">
            @if ($action['url'] !== null)
                <a href="{{ $action['url'] }}" @class([
                    'w-full text-center after:absolute after:inset-0 sm:w-auto',
                    'cc-btn-primary' => $primary,
                    'cc-btn-secondary' => ! $primary,
                ])>{{ $action['label'] }}</a>
            @else
                <p class="rounded-md bg-[#F8F9FA] px-4 py-3 text-sm font-semibold text-[#4A5568] dark:bg-slate-800 dark:text-slate-300">{{ $action['label'] }}</p>
            @endif
        </div>
    </div>
</article>
