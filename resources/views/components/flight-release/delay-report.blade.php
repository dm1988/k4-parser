@props(['text'])

<section class="flex flex-col gap-3 rounded-xl border border-[#1B365D]/15 p-4 dark:border-slate-700" aria-labelledby="delay-report-heading">
    <h3 id="delay-report-heading" class="font-bold text-[#1B365D] dark:text-slate-100">Report text</h3>
    <textarea id="flight-delay-report" readonly rows="18" aria-label="Flight delay report text" class="w-full rounded-lg border-slate-300 bg-[#F8F9FA] font-mono text-sm text-[#0B0E14] focus:border-[#C5A059] focus:ring-[#C5A059] dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">{{ $text }}</textarea>
    <div class="flex flex-wrap items-center gap-3">
        <x-flight-release.copy-button target="flight-delay-report" label="Report text" status="flight-delay-copy-status" text="Copy report text" class="focus-visible:ring-2 focus-visible:ring-[#C5A059]" />
        <span id="flight-delay-copy-status" role="status" aria-live="polite" class="text-sm text-[#4A5568] dark:text-slate-300"></span>
    </div>
    <p class="text-xs text-[#4A5568] dark:text-slate-400">If copying is unavailable, select the report text and copy it manually.</p>
</section>
