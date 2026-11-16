<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100">My bid predictions</h1></x-slot>
    <div class="mx-auto max-w-4xl space-y-6 px-4 py-6 text-slate-900 sm:px-6 dark:text-slate-100">
        <div class="space-y-2">
            <a href="{{ route('crew-bids.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold underline focus-visible:ring-2 focus-visible:ring-amber-500">Back to K4 Bids</a>
            <p class="font-mono text-sm">{{ $package->period }} · B{{ $package->fleet }} · {{ $seat }} · {{ $package->source_revision }}</p>
            <p class="text-sm text-slate-600 dark:text-slate-300">Kalitta View All Bids is shared across K4 Bids users. Read the latest stored bids without a portal login. Your private list and submission status remain separate.</p>
        </div>
        @if (session('crew_bid_sync_error'))<p role="alert" class="rounded-lg border border-amber-500 p-4 text-sm">{{ session('crew_bid_sync_error') }} The last successful snapshot remains visible below.</p>@endif
        @if (session('crew_bid_sync_notice'))<p role="status" class="rounded-lg border border-[#C5A059] p-4 text-sm">{{ session('crew_bid_sync_notice') }}</p>@endif
        @if ($refresh)
            <section class="space-y-3 rounded-xl border border-[#C5A059] p-5" aria-label="Shared-bid refresh status"
                x-data="createCrewBidRefresh(@js(['url' => route('crew-bids.employees.refresh.status', ['package' => $package, 'refresh' => $refresh['id']]), 'state' => $refresh['state'], 'message' => $refresh['message']]))">
                <p role="status" aria-live="polite" class="text-sm" x-text="message">{{ $refresh['message'] }}</p>
                <p x-show="connectionError" x-cloak class="text-sm">Unable to check progress. Retrying automatically; your refresh may still be running.</p>
                <div x-show="state === 'completed'" @if ($refresh['state'] !== 'completed') x-cloak @endif class="flex flex-wrap gap-3">
                    <a href="{{ route('crew-bids.employees.index', ['package' => $package, 'seat' => $seat]) }}" class="inline-flex min-h-11 items-center rounded-lg bg-[#1B365D] px-4 text-sm font-semibold text-white focus-visible:ring-2 focus-visible:ring-amber-500">View updated prediction</a>
                    @if ($refresh['format'])<a href="{{ route('crew-bids.employees.refresh.download', ['package' => $package, 'refresh' => $refresh['id']]) }}" class="inline-flex min-h-11 items-center rounded-lg border border-slate-300 px-4 text-sm font-semibold focus-visible:ring-2 focus-visible:ring-amber-500 dark:border-slate-700">Download {{ $refresh['format'] }}</a>@endif
                </div>
                <p x-show="state === 'failed'" @if ($refresh['state'] !== 'failed') x-cloak @endif class="text-sm">The last successful snapshot remains available. <a href="#refresh-shared-bids" @click="document.getElementById('refresh-shared-bids').open = true" class="font-semibold underline focus-visible:ring-2 focus-visible:ring-amber-500">Enter your portal login to retry</a>.</p>
                <noscript><p class="text-sm">Reload this page to check progress. Your refresh continues in the background.</p></noscript>
            </section>
        @endif
        <section class="space-y-4 rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900" aria-labelledby="shared-bids-title">
            <h2 id="shared-bids-title" class="text-lg font-semibold">Your prediction</h2>
            <form method="GET" action="{{ route('crew-bids.employees.index', $package) }}" class="flex flex-wrap items-end gap-3">
                <label class="block text-sm font-medium">Position<select name="seat" class="mt-1 block min-h-11 rounded-lg dark:bg-slate-950"><option value="CA" @selected($seat === 'CA')>CA</option><option value="FO" @selected($seat === 'FO')>FO</option></select></label>
                <button type="submit" class="min-h-11 rounded-lg bg-[#1B365D] px-4 text-sm font-semibold text-white focus-visible:ring-2 focus-visible:ring-amber-500">Show my prediction</button>
            </form>
            <p class="text-sm">{{ $prediction['reason'] }}</p>
            @if ($prediction['seniority_date'])<p class="font-mono text-xs">Seniority list as of {{ $prediction['seniority_date'] }}</p>@endif
            @if ($snapshot)
                <p class="font-mono text-xs">Source: Kalitta View All Bids · {{ $prediction['checked_at'] }}{{ $prediction['stale'] ? ' · Stale—refresh recommended' : '' }}</p>
                <p class="font-mono text-xs text-slate-500 dark:text-slate-400">Portal package {{ $snapshot->portal_package_id }} · Revision {{ $snapshot->portal_revision }}</p>
                <p class="font-semibold">{{ $prediction['likely'] ? 'Likely hold: '.$prediction['likely'] : 'Likely hold: not established' }}</p>
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $prediction['basis'] }}. This is a projection, not an official award. Later bids, eligibility and training rules may change the outcome.</p>
                @if ($person)
                    <h3 class="font-semibold">Your submitted choices · {{ $person['name'] }}</h3>
                    @if ($person['numbers'] === [])<p>No submitted choices in the shared report for your employee number.</p>@else
                        <div class="overflow-x-auto"><table class="w-full text-left text-sm"><caption class="sr-only">Original submitted choice order for {{ $person['name'] }}</caption><thead><tr><th scope="col" class="p-2">Choice</th><th scope="col" class="p-2">Portal line</th></tr></thead><tbody class="divide-y divide-slate-100 font-mono dark:divide-slate-800">@foreach ($person['numbers'] as $rank => $number)<tr><td class="p-2">{{ $person['ranks'][$rank] ?? $rank + 1 }}</td><td class="p-2">{{ $number }}</td></tr>@endforeach</tbody></table></div>
                    @endif
                @endif
            @else
                <p role="status">No shared bid snapshot for this month, fleet and position. Refresh from Kalitta below to make the current bids available to everyone.</p>
            @endif
        </section>
        <section class="space-y-3 rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900" aria-labelledby="employee-link-title">
            <h2 id="employee-link-title" class="font-semibold">Link your employee number</h2>
            @if ($prediction['employee_name'])<p role="status" class="text-sm font-semibold">Linked: {{ $prediction['employee_name'] }} · seniority {{ $prediction['employee_seniority'] }}</p>@endif
            <p class="text-sm">Verify your Kalitta login once. Future predictions find you automatically using the imported seniority list. You can verify again to correct an existing link.</p>
            <form method="POST" action="{{ route('crew-bids.employees.read', $package) }}" autocomplete="off" class="space-y-4" x-data="{ busy: false }" @pageshow.window="busy = false" @submit="if (busy) { $event.preventDefault(); return; } busy = true; setTimeout(() => { $el.elements.employee.value = ''; $el.elements.portal_password.value = ''; }, 0)">
                @csrf
                <input type="hidden" name="seat" value="{{ $seat }}">
                <input type="hidden" name="operation" value="link">
                <x-crew-bids.portal-credentials :viewing="true" id-prefix="link" />
                <button type="submit" :disabled="busy" class="min-h-11 rounded-lg bg-[#1B365D] px-4 text-sm font-semibold text-white focus-visible:ring-2 focus-visible:ring-amber-500 disabled:opacity-50"><span x-show="!busy">Verify & link my employee number</span><span x-show="busy" x-cloak role="status">Verifying…</span></button>
            </form>
        </section>
        <details id="refresh-shared-bids" x-data x-init="$el.open = window.location.hash === '#refresh-shared-bids'" class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900">
            <summary class="min-h-7 cursor-pointer rounded font-semibold focus-visible:ring-2 focus-visible:ring-amber-500">Refresh shared bids / portal exports</summary>
            <p class="mt-3 text-sm text-slate-600 dark:text-slate-300">A refresh reads every page of the global {{ $seat }} report in the background and updates it for all users. You can keep using the app while it runs. Failed report validation keeps the previous snapshot. Exports become available here when complete for 15 minutes.</p>
            <form method="POST" action="{{ route('crew-bids.employees.read', $package) }}" autocomplete="off" class="mt-4 space-y-4" x-data="{ busy: false }" @pageshow.window="busy = false" @submit="if (busy) { $event.preventDefault(); return; } busy = true; const form = $el; setTimeout(() => { form.elements.employee.value = ''; form.elements.portal_password.value = ''; }, 0)">
                @csrf
                <input type="hidden" name="seat" value="{{ $seat }}">
                <x-crew-bids.portal-credentials :viewing="true" id-prefix="refresh" />
                <div class="flex flex-wrap gap-3">
                    <button type="submit" :disabled="busy" class="min-h-11 rounded-lg bg-[#1B365D] px-4 py-2 text-sm font-semibold text-white focus-visible:ring-2 focus-visible:ring-amber-500 disabled:opacity-50">Refresh shared {{ $seat }} bids</button>
                    @foreach (['PDF', 'Excel'] as $format)<button type="submit" :disabled="busy" name="format" value="{{ $format }}" class="min-h-11 rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold focus-visible:ring-2 focus-visible:ring-amber-500 disabled:opacity-50 dark:border-slate-700">Refresh & prepare {{ $format }}</button>@endforeach
                </div>
                <p x-show="busy" x-cloak role="status" class="text-sm">Queueing refresh…</p>
            </form>
        </details>
    </div>
</x-app-layout>
