@props(['model'])

<div class="grid gap-3 p-3 sm:p-4">
    @foreach ($model->dispatcherNotes() as $note)
        <article class="rounded-lg border border-[#1B365D]/10 bg-[#F8F9FA] p-4 text-sm leading-6 text-[#0B0E14] shadow-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
            <p class="whitespace-pre-wrap">{{ $note }}</p>
        </article>
    @endforeach
</div>
