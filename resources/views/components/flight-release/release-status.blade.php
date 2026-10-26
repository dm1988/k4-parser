@props(['status'])

@if ($status['label'])
    <aside {{ $attributes->class([
        'flex flex-wrap items-center gap-x-3 gap-y-1 rounded-lg border px-4 py-3 text-sm',
        'border-amber-300/70 bg-amber-50 text-amber-950 dark:border-amber-400/25 dark:bg-amber-400/10 dark:text-amber-100' => $status['preliminary'],
        'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200' => ! $status['preliminary'],
    ]) }} aria-label="Release status">
        <span class="font-bold">{{ $status['label'] }}</span>
        <p>{{ $status['warning'] }}</p>
    </aside>
@endif
