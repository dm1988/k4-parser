<div wire:key="awards-workspace-{{ $report['id'] }}" class="mx-auto max-w-7xl px-3 py-6 text-slate-900 dark:text-slate-100 sm:px-6" data-bid-awards>
    <header class="sticky top-0 z-30 -mx-3 flex flex-wrap items-center gap-3 border-b border-slate-200 bg-gray-100 px-3 py-3 dark:border-slate-800 dark:bg-gray-950 sm:-mx-6 sm:px-6">
        <h1 class="text-2xl font-bold text-[#1B365D] dark:text-amber-200">K4 Bids</h1>
        <label for="award-context" class="sr-only">Bid package or awards</label>
        <select id="award-context" wire:model="contextPackage" wire:change="changeContext" wire:loading.attr="disabled" wire:target="changeContext" class="min-h-11 min-w-0 max-w-full rounded-full border-slate-300 bg-white font-mono text-xs focus-visible:ring-2 focus-visible:ring-amber-500 dark:border-slate-700 dark:bg-slate-900 sm:text-sm">
            @foreach ($packages as $option)<option value="{{ $option->id }}">{{ $option->period }} · B{{ $option->fleet }} · {{ $option->source_revision }}</option>@endforeach
            @foreach ($awardReports as $option)<option value="awards:{{ $option->id }}">{{ $option->period }} · B{{ $option->fleet }} · Awards #{{ $option->id }}</option>@endforeach
        </select>
        @can('manage-crew-bids')<a href="{{ route('filament.admin.resources.bid-award-reports.index') }}" aria-label="Manage awards" title="Manage awards" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100 focus-visible:ring-2 focus-visible:ring-amber-500 dark:text-slate-400 dark:hover:bg-slate-800"><x-heroicon-o-cog-6-tooth class="h-4 w-4" aria-hidden="true" /></a>@endcan
    </header>
    @if ($notice)<p role="status" class="my-3 text-sm text-slate-600 dark:text-slate-300">{{ $notice }}</p>@endif
    <main class="my-6 space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div><h2 class="text-xl font-bold">Your award{{ $awards->lineLabel ? ' · '.$awards->lineLabel : '' }}</h2><p class="mt-1 font-mono text-sm text-slate-600 dark:text-slate-300">{{ $report['period'] }} · B{{ $report['fleet'] }} · UTC</p></div>
            @if ($awards->result->parsed['calendar_events'] !== [])<a href="{{ route('crew-bids.awards.calendar', ['report' => $report['id']]) }}" class="inline-flex min-h-11 items-center gap-2 rounded-lg bg-[#1B365D] px-4 text-sm font-semibold text-white focus-visible:ring-2 focus-visible:ring-amber-500"><x-heroicon-o-calendar-days class="h-5 w-5" aria-hidden="true" />Download duty &amp; flights .ics</a>@endif
        </div>
        @if ($awards->employeeRequired || ! $awards->matched)
            <section class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900" aria-label="Identify your award">
                <p class="text-sm">{{ $awards->employeeRequired ? 'Enter your employee number (Crew ID) to find your award.' : 'No award was found for your employee number in this report. Check the month, fleet, and Crew ID.' }}</p>
                @include('crew-bids.award-employee-form')
            </section>
        @else
            <section class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900" aria-label="Crew awarded your line">
                <h3 class="font-semibold">Crew awarded the same line</h3>
                <ul class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($awards->crew as $person)<li class="flex items-center gap-2 rounded-lg bg-slate-50 px-3 py-2 text-sm dark:bg-slate-800"><span class="font-mono font-semibold text-[#1B365D] dark:text-amber-200">{{ $person['seat'] }}</span><span>{{ $person['name'] }}</span>@if ($person['isUser'])<span class="text-xs text-slate-500 dark:text-slate-400">You</span>@endif</li>@endforeach
                </ul>
                <details class="mt-4 text-sm"><summary class="min-h-8 cursor-pointer text-slate-600 dark:text-slate-300">Use a different Crew ID for uploaded awards</summary>@include('crew-bids.award-employee-form')</details>
            </section>
            @foreach ($awards->warnings as $warning)<p role="status" class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-200">{{ $warning }}</p>@endforeach
            @if ($awards->days !== [])
                <section class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900" aria-label="Award calendar with transition days">
                    <h3 class="font-semibold">Award calendar · UTC</h3>
                    <p class="mt-1 text-xs text-slate-600 dark:text-slate-300">The full month is followed by this line’s next-month transition days. Occupied trip dates include layover/rest; they are not all duty days.</p>
                    <div class="mt-4 grid grid-cols-7 gap-1 sm:gap-2">
                        @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday)<span class="text-center text-xs text-slate-500 dark:text-slate-400">{{ $weekday }}</span>@endforeach
                        @for ($offset = 0; $offset < $awards->calendarOffset; $offset++)<span aria-hidden="true"></span>@endfor
                        @foreach ($awards->days as $day)
                            <div data-award-date="{{ $day['date'] }}" @class(['min-h-24 min-w-0 rounded-lg border p-1.5 text-xs sm:p-3', 'ring-2 ring-inset ring-[#C5A059]' => $day['transition'], 'border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-950/40' => in_array($day['status'], ['occupied', 'duty']), 'border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800' => $day['status'] === 'off', 'border-dashed border-slate-300 dark:border-slate-600' => $day['status'] === 'unknown'])>
                                <p class="text-slate-500 dark:text-slate-400">{{ $day['weekday'] }}</p><time datetime="{{ $day['date'] }}" aria-label="{{ $day['date'] }} UTC" class="mt-1 block font-mono font-semibold">{{ $day['label'] }}</time>
                                @if ($day['transition'])<span class="mt-1 block font-semibold text-[#1B365D] dark:text-amber-200">Transition / carry</span>@endif
                                <p class="mt-2 break-words text-[10px] sm:text-xs">{{ $day['activity'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
            @if ($awards->duties !== [])
                <section aria-label="Awarded duty and work periods"><h3 class="mb-3 font-semibold">Duty and work periods · UTC</h3><ul class="space-y-2">@foreach ($awards->duties as $duty)@if ($duty)<li class="rounded-lg border border-slate-200 bg-white p-4 text-sm dark:border-slate-700 dark:bg-slate-900"><p class="font-semibold">{{ $duty->title }}</p><p class="mt-1 font-mono text-xs">{{ $duty->scheduleLabel }} · {{ $duty->durationLabel }}</p></li>@endif @endforeach</ul></section>
            @endif
            @if ($awards->flights !== [])<section aria-label="Awarded flights"><h3 class="mb-3 font-semibold">Flights · UTC</h3><div class="grid gap-4 lg:grid-cols-2">@foreach ($awards->flights as $flight)<x-extract.flight-card :model="$flight" />@endforeach</div></section>@endif
        @endif
    </main>
</div>
