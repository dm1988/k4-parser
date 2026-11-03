@props(['editor'])
<dialog x-data="{ returnTo: null }" x-effect="if ($wire.showPayEditor && !$el.open) { returnTo = document.activeElement; $el.showModal() } else if (!$wire.showPayEditor && $el.open) { $el.close() }" @cancel.prevent="$wire.closePayEditor()" @keydown.escape.stop @close="if (returnTo?.isConnected) returnTo.focus(); else document.querySelector('[x-ref=payTrigger]')?.focus()" wire:ignore.self aria-labelledby="bid-pay-title" class="fixed inset-0 m-auto max-h-[90dvh] w-[calc(100%_-_1rem)] max-w-3xl overflow-y-auto rounded-xl border border-slate-200 bg-white p-0 text-slate-900 shadow-xl backdrop:bg-slate-950/50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
    @if ($editor->open)
        <form x-data="bidAction" @submit.prevent="run(() => $wire.savePayInputs())" @bid-action-failed="$refs.payNetworkError.hidden = false" class="space-y-5 p-4 sm:p-6">
            <header class="flex items-center justify-between gap-3"><h2 id="bid-pay-title" class="text-lg font-semibold">{{ $editor->title }}</h2><button type="button" autofocus wire:click="closePayEditor" class="min-h-11 rounded px-2 underline focus-visible:ring-2 focus-visible:ring-amber-500">Close</button></header>
            <p class="font-mono text-sm">{{ $editor->period }}</p>
            <p x-ref="payNetworkError" hidden role="alert" class="rounded-lg border border-amber-500 p-3 text-sm text-amber-900 dark:text-amber-200">Pay inputs could not be saved. Your edits are retained. Check the connection and retry saving.</p>
            <p class="text-sm text-slate-600 dark:text-slate-300">USD scheduled gross before taxes/deductions, excluding per diem and expenses. These private planning inputs are saved only to your account. Estimates use saved inputs until you save again.</p>
            @if ($errors->any())<div role="alert" class="rounded-lg border border-amber-500 p-3 text-sm text-amber-900 dark:text-amber-200">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
            <section class="space-y-2 text-sm" aria-label="Profile pay settings">
                <h3 class="font-semibold">From your user profile</h3>
                <p>Adjusted pay-longevity date (UTC): {{ $editor->longevityLabel }}</p>
                <p>Check Airman: {{ $editor->checkAirmanLabel }}</p>
                <a href="{{ route('profile.edit') }}#k4-bids-preferences" class="inline-flex min-h-11 items-center rounded px-2 font-semibold underline focus-visible:ring-2 focus-visible:ring-amber-500">Edit pay settings in your profile</a>
            </section>
            <p class="text-sm">Pay is shown automatically. F lines are hard lines; Q lines are reserve. Estimates assume qualified hourly pay, full-period availability and published line Credit as the minimum. No setup is required here.</p>
            <details class="rounded-lg border border-slate-200 p-3 dark:border-slate-700"><summary class="min-h-11 cursor-pointer py-2 font-semibold">Advanced adjustments (optional)</summary>
            <fieldset class="space-y-2"><legend class="font-semibold">Normal assumptions</legend>
                @foreach ($editor->contextChoices as $field => $label)
                    <label class="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" wire:model="payContext.{{ $field }}" @change="$wire.payContext.custom_assumptions = true" class="rounded">{{ $label }}</label>
                @endforeach
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $editor->unsupportedHelp }}</p>
            </fieldset>
            @if ($editor->lineId !== null)
                <fieldset class="space-y-3"><legend class="font-semibold">{{ $editor->lineId }} credit and additional pay</legend>
                    <p class="text-sm font-semibold">{{ $editor->lineType }}</p>
                    <label class="block text-sm">Ordinary scheduled-credit basis<select wire:model="payInput.mode" class="mt-1 block min-h-11 w-full rounded-lg dark:bg-slate-800">@foreach ($editor->creditModes as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Use private dated inputs for reserve, carry allocation, a trip spanning a rate/compensation boundary, or incomplete source coverage. Each assignment/day appears once. For carry, enter whole daily awarded PCH and optional actual PCH; the greater applies. Full flight segments use the departure UTC day. All rows allocate to this package's compensation period; earlier earning dates retain their earning-day rate.</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Source mode accepts separate additional pay only. Open Time flight on a genuine scheduled duty-free day requires private mode: omit that assignment from ordinary PCH and enter only its hours flown as Open Time. Its entire additional payment is 1.5×. A day you requested off does not itself prove SDFDO eligibility.</p>
                    <div class="space-y-3">
                        @foreach ($editor->rows as $index => $row)
                            <fieldset wire:key="pay-row-{{ $editor->lineId }}-{{ $index }}" class="grid gap-3 rounded-lg border border-slate-200 p-3 sm:grid-cols-2 dark:border-slate-700"><legend class="px-1 text-sm">Input {{ $index + 1 }}</legend>
                                <label class="text-sm">Assignment reference<input wire:model="payInput.rows.{{ $index }}.assignment" required maxlength="80" class="mt-1 block min-h-11 w-full rounded-lg dark:bg-slate-800"></label>
                                <label class="text-sm">Pay category<select wire:model="payInput.rows.{{ $index }}.type" class="mt-1 block min-h-11 w-full rounded-lg text-xs dark:bg-slate-800">@foreach ($editor->payTypes as $type => $label)<option value="{{ $type }}">{{ $label }}</option>@endforeach</select></label>
                                <label class="text-sm">Earning/departure date (UTC)<input type="date" wire:model="payInput.rows.{{ $index }}.date" required class="mt-1 block min-h-11 w-full rounded-lg dark:bg-slate-800"></label>
                                <label class="text-sm">Duration (h:mm[:ss], PCH where labelled)<input wire:model="payInput.rows.{{ $index }}.duration" required placeholder="4:00" class="mt-1 block min-h-11 w-full rounded-lg font-mono dark:bg-slate-800"></label>
                                <label class="text-sm">Carry actual PCH, if known (optional)<input wire:model="payInput.rows.{{ $index }}.actual" placeholder="Unknown" class="mt-1 block min-h-11 w-full rounded-lg font-mono dark:bg-slate-800"></label>
                                <button type="button" wire:click="removePayRow({{ $index }})" class="min-h-11 self-end rounded px-2 text-sm underline focus-visible:ring-2 focus-visible:ring-amber-500">Remove input {{ $index + 1 }}</button>
                            </fieldset>
                        @endforeach
                    </div>
                    <button type="button" wire:click="addPayRow" class="min-h-11 rounded-lg border border-slate-300 px-3 text-sm font-semibold focus-visible:ring-2 focus-visible:ring-amber-500 dark:border-slate-600">Add credit / additional pay</button>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Fixed awards (vacation/training/CQ CBT/tested CBT/random test) use their labelled entitlement, regardless of the duration field; enter 0:00 when duration does not apply. Passenger deadhead uses scheduled block at 50%; taxi-only has a 1 PCH minimum. Maintenance rounds time up to each started hour before applying $25. Simulator instruction uses brief/debrief-inclusive duration at the minimum $15/h and requires a non-Check-Airman scenario.</p>
                </fieldset>
            @else
                <p class="text-sm">To adjust a particular line, open its Details → Estimated pay → Edit pay inputs. Normal estimates already use your profile and package data.</p>
            @endif
            </details>
            <footer class="flex flex-wrap items-center gap-3 border-t border-slate-200 pt-4 dark:border-slate-700">
                <button type="submit" wire:loading.attr="disabled" wire:target="savePayInputs" class="min-h-11 rounded-lg bg-[#1B365D] px-4 font-semibold text-white focus-visible:ring-2 focus-visible:ring-amber-500"><span wire:loading.remove wire:target="savePayInputs">Save and recalculate</span><span wire:loading wire:target="savePayInputs" role="status">Saving…</span></button>
                <button type="button" wire:click="forgetPayInputs" wire:confirm="Remove your pay display settings and package scenarios? Your profile longevity, Check Airman setting and bid list will be retained." class="min-h-11 rounded px-2 text-sm underline focus-visible:ring-2 focus-visible:ring-amber-500">Remove comparison pay inputs</button>
                <p class="text-xs text-slate-500 dark:text-slate-400">Saving retains your filters, selected bids and manual order.</p>
            </footer>
        </form>
    @endif
</dialog>
