<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold">Submit to Kalitta</h1></x-slot>
    <section class="mx-auto my-6 max-w-2xl space-y-5 rounded-xl bg-white p-5 shadow dark:bg-slate-900">
        <a href="{{ route('crew-bids.index') }}" class="inline-flex min-h-11 items-center underline focus-visible:ring-2 focus-visible:ring-amber-500">Back to K4 Bids</a>
        <p class="font-mono">{{ $package->period }} · B{{ $package->fleet }} · {{ $workspace->seat }}</p>
        <p class="text-sm">Local package: {{ $package->source_revision }}. This submits your saved order. First compare it with Kalitta's current submitted report.</p>
        @if (session('bid_submission_error'))<p role="alert" class="rounded-lg bg-amber-50 p-3 text-amber-900 dark:bg-amber-950 dark:text-amber-200">{{ session('bid_submission_error') }}</p>@endif
        <ol class="list-inside list-decimal space-y-1 font-mono text-sm">@foreach ($workspace->selections as $line)<li>{{ $line }}</li>@endforeach</ol>
        @if ($pending)
            <p role="status">A previous submission has an unknown outcome. Check its status before another update.</p>
            <a href="{{ route('crew-bids.submissions.show', $pending) }}" class="inline-flex min-h-11 items-center rounded-lg bg-[#1B365D] px-4 font-semibold text-white focus-visible:ring-2 focus-visible:ring-amber-500">Check previous submission</a>
        @elseif ($workspace->selections === [])
            <p role="status">Add and save your bid choices before submitting.</p>
        @else
            <form method="POST" action="{{ route('crew-bids.submissions.review', ['package' => $package, 'seat' => $workspace->seat]) }}" autocomplete="off" x-data="{ busy: false }" @submit="busy = true" class="space-y-4">
                @csrf
                <input type="hidden" name="revision" value="{{ $workspace->revision }}">
                <x-crew-bids.portal-credentials />
                <button type="submit" :disabled="busy" class="min-h-11 rounded-lg bg-[#1B365D] px-4 font-semibold text-white focus-visible:ring-2 focus-visible:ring-amber-500 disabled:opacity-50"><span x-show="!busy">Compare with Kalitta</span><span x-show="busy" x-cloak role="status">Reading portal report…</span></button>
            </form>
        @endif
    </section>
</x-app-layout>
