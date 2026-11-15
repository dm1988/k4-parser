<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold">Kalitta bid submission</h1></x-slot>
    <section class="mx-auto my-6 max-w-3xl space-y-5 rounded-xl bg-white p-5 shadow dark:bg-slate-900">
        <a href="{{ route('crew-bids.index') }}" class="inline-flex min-h-11 items-center underline focus-visible:ring-2 focus-visible:ring-amber-500">Back to K4 Bids</a>
        <p class="font-mono">{{ $review['context']['period'] }} · B{{ $review['context']['fleet'] }} · {{ $review['context']['seat'] }}</p>
        <p class="text-sm">Local release: {{ $review['local_revision'] }} · Portal revision: {{ $review['context']['package_revision'] }}. Portal account matched the employee number entered.</p>
        @if (session('bid_submission_error'))<p role="alert" class="rounded-lg bg-amber-50 p-3 text-amber-900 dark:bg-amber-950 dark:text-amber-200">{{ session('bid_submission_error') }}</p>@endif
        @if ($submission->status === 'matched')
            <p role="status" class="rounded-lg bg-emerald-50 p-3 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">The fresh Kalitta report matches every choice in your reviewed order.</p>
            <p class="text-sm">This verifies the report order. Receipt email and awarded line are separate; check Kalitta's Bid Received email for acceptance confirmation.</p>
        @elseif ($submission->status === 'not_matched')
            <p role="status">The fresh report contains a different order. Review it in Kalitta, then start a new comparison before another update.</p>
        @elseif ($submission->status === 'unknown')
            <p role="alert" class="rounded-lg bg-amber-50 p-3 text-amber-900 dark:bg-amber-950 dark:text-amber-200">Submission status unknown. The update may have reached Kalitta. Check the fresh report before trying another submission.</p>
            <form method="POST" action="{{ route('crew-bids.submissions.reconcile', $submission) }}" autocomplete="off" x-data="{ busy: false }" @submit="busy = true" class="space-y-4">
                @csrf
                <x-crew-bids.portal-credentials />
                <button type="submit" :disabled="busy" class="min-h-11 rounded-lg bg-[#1B365D] px-4 font-semibold text-white focus-visible:ring-2 focus-visible:ring-amber-500 disabled:opacity-50"><span x-show="!busy">Check portal report only</span><span x-show="busy" x-cloak role="status">Checking report…</span></button>
            </form>
        @elseif ($submission->status !== 'reviewed' || $submission->expires_at->isPast())
            <p role="status">This comparison expired. Return to your bid list and compare again.</p>
        @else
            <p class="text-sm">{{ count($review['added']) }} added · {{ count($review['removed']) }} removed · {{ $review['reordered'] ? 'Retained choices reordered' : 'Retained order unchanged' }}. The portal reports an open bid window; it does not provide a verified closing time here.</p>
            <div class="grid gap-5 sm:grid-cols-2">
                <div><h2 class="font-semibold">Current portal report</h2><ol class="mt-2 list-inside list-decimal space-y-1 font-mono text-sm">@foreach ($review['before'] as $number)<li>{{ $number }}</li>@endforeach</ol></div>
                <div><h2 class="font-semibold">Replace with this exact order</h2><ol class="mt-2 list-inside list-decimal space-y-1 font-mono text-sm">@foreach ($review['selections'] as $index => $line)<li>{{ $line }} → {{ $review['numbers'][$index] }}</li>@endforeach</ol></div>
            </div>
            <p class="text-sm">Old trailing choices will be cleared. Other portal settings will be preserved. A changed saved list or portal context requires another review.</p>
            <form method="POST" action="{{ route('crew-bids.submissions.submit', $submission) }}" autocomplete="off" x-data="{ busy: false }" @submit="busy = true" class="space-y-4">
                @csrf
                <x-crew-bids.portal-credentials />
                <label class="flex items-start gap-3 text-sm"><input name="confirmed" type="checkbox" value="1" required class="mt-1 rounded border-slate-300 text-[#1B365D] focus:ring-[#C5A059]"><span>I confirm this account, bid month, fleet, seat and package release match my intended bid; I am eligible for these choices and want to replace my Kalitta bid with the complete order shown.</span></label>
                <button type="submit" :disabled="busy" class="min-h-11 rounded-lg bg-[#C5A059] px-4 font-semibold text-[#0B0E14] focus-visible:ring-2 focus-visible:ring-amber-500 disabled:opacity-50"><span x-show="!busy">Submit this bid to Kalitta</span><span x-show="busy" x-cloak role="status">Submitting and checking report…</span></button>
            </form>
        @endif
    </section>
</x-app-layout>
