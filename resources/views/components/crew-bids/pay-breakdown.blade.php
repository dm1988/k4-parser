@props(['estimate', 'lineId'])
<details class="mt-3 rounded-lg border border-slate-200 p-3 text-sm dark:border-slate-700">
    <summary class="cursor-pointer rounded py-1 font-semibold focus-visible:ring-2 focus-visible:ring-amber-500">Estimated pay · {{ $estimate->label }}</summary>
    <div class="mt-3 space-y-3">
        <p>{{ $estimate->period }} · USD scheduled gross</p>
        @if (! $estimate->ready)
            <p class="text-amber-800 dark:text-amber-300">A complete total is unavailable. Known components below are not a complete pay estimate.</p>
            <ul class="list-disc space-y-1 ps-5">@foreach ($estimate->missing as $message)<li>{{ $message }}</li>@endforeach</ul>
        @endif
        <button type="button" wire:click="openPayEditor('{{ $lineId }}')" aria-haspopup="dialog" class="min-h-11 rounded px-2 font-semibold underline focus-visible:ring-2 focus-visible:ring-amber-500">Edit pay inputs for {{ $lineId }}</button>
        @if ($estimate->components !== [])
            <dl class="space-y-3">
                @foreach ($estimate->components as $component)
                    <div><dt>{{ $component['label'] }}</dt><dd class="font-mono font-semibold">{{ $component['amount'] }}</dd><dd class="text-xs text-slate-500 dark:text-slate-400">{{ $component['basis'] }}</dd></div>
                @endforeach
            </dl>
            <details><summary class="cursor-pointer py-2 underline">Rates, sources and assumptions</summary><div class="space-y-2 text-xs text-slate-600 dark:text-slate-300">
                @foreach ($estimate->rates as $rate)<p>{{ $rate }}</p>@endforeach
                @foreach ($estimate->assumptions as $assumption)<p>{{ $assumption }}</p>@endforeach
                <p>Rules: {{ $estimate->version }}. The user-confirmed longevity, proration and Open Time interpretations are scenario assumptions; the Sections 2/5 review does not independently verify them.</p>
            </div></details>
        @endif
    </div>
</details>
