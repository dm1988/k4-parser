<div class="mx-auto max-w-7xl px-3 py-6 sm:px-6" data-crew-bids data-dirty="{{ $dirty ? 'true' : 'false' }}" x-data="crewBidsHeader" :data-preferences-dirty="$wire.$dirty(['goals', 'daysOff', 'startCity'])" @keydown.escape.window="if ($wire.showList) { $wire.showList = false; $refs.listTrigger?.focus() } else if (filtersOpen) { filtersOpen = false; $refs.filtersTrigger?.focus() }">
    <header x-ref="workspaceHeader" class="sticky top-0 z-30 -mx-3 flex flex-wrap items-center gap-3 border-b border-slate-200 bg-gray-100 px-3 py-3 shadow-sm dark:border-slate-800 dark:bg-gray-950 sm:-mx-6 sm:px-6">
        <h1 class="text-2xl font-bold text-[#1B365D] dark:text-amber-200">K4 Bids</h1>
        @if ($packages->isNotEmpty())
            <div class="order-2 flex w-full min-w-0 max-w-full items-center gap-1 sm:order-1 sm:w-auto">
            <label class="sr-only" for="bid-package">Bid package</label>
            <select id="bid-package" wire:model="contextPackage" wire:change="changeContext" class="min-w-0 max-w-full rounded-lg border-slate-300 bg-white text-sm dark:border-slate-600 dark:bg-slate-900">
                @foreach ($packages as $option)<option value="{{ $option->id }}">{{ $option->period }} · B{{ $option->fleet }} · {{ $option->source_revision }}</option>@endforeach
            </select>
            @if ($package)<button type="button" x-ref="packageDetailsTrigger" @click="$refs.packageDetails.showModal()" aria-label="Open package details" aria-haspopup="dialog" aria-controls="bid-package-details" title="Package details" class="inline-flex min-h-11 min-w-11 shrink-0 items-center justify-center rounded-full text-slate-500 hover:bg-slate-200 focus-visible:ring-2 focus-visible:ring-amber-500 dark:text-slate-400 dark:hover:bg-slate-800"><x-heroicon-o-information-circle class="h-5 w-5" aria-hidden="true" /></button>@endif
            </div>
            <label class="sr-only" for="bid-seat">Seat</label>
            <select id="bid-seat" wire:model="contextSeat" wire:change="changeContext" class="order-3 rounded-lg border-slate-300 bg-white text-sm dark:border-slate-600 dark:bg-slate-900 sm:order-2"><option value="FO">First Officer</option><option value="CA">Captain</option></select>
        @endif
        <button type="button" x-ref="listTrigger" wire:click="$set('showList', true)" class="order-1 ms-auto inline-flex min-h-11 items-center gap-2 rounded-lg bg-[#1B365D] px-4 text-sm font-semibold text-white focus-visible:ring-2 focus-visible:ring-amber-500 sm:order-4"><x-heroicon-o-shopping-cart class="h-5 w-5 shrink-0" aria-hidden="true" /><span>My bid list ({{ count($selections) }})</span></button>
        @can('manage-crew-bids')<button type="button" wire:click="$toggle('showImport')" class="order-4 inline-flex min-h-11 items-center gap-2 rounded-lg px-3 text-sm underline focus-visible:ring-2 focus-visible:ring-amber-500 sm:order-5"><x-heroicon-o-cog-6-tooth class="h-4 w-4 shrink-0" aria-hidden="true" /><span>Manage package</span></button>@endcan
        <span class="order-5 text-xs text-slate-500 dark:text-slate-400 sm:order-6" role="status" aria-live="polite"><span wire:loading>Updating…</span><span wire:loading.remove>{{ $dirty ? 'Unsaved changes' : ($revision > 0 ? 'Saved' : '') }}</span></span>
    </header>

    @if ($package)
        <dialog id="bid-package-details" x-ref="packageDetails" wire:ignore.self aria-labelledby="bid-package-details-title" @keydown.escape.stop @click="if ($event.target === $el) $el.close()" @close="$refs.packageDetailsTrigger.focus()" class="fixed inset-0 m-auto max-h-[80dvh] w-[calc(100%_-_2rem)] max-w-lg overflow-y-auto rounded-xl border border-slate-200 bg-white p-0 text-slate-900 shadow-xl backdrop:bg-slate-950/50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
            <section class="p-5">
                <div class="flex items-center justify-between gap-3"><h2 id="bid-package-details-title" class="text-lg font-semibold">Package details</h2><button type="button" autofocus @click="$refs.packageDetails.close()" aria-label="Close package details" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-full hover:bg-slate-100 focus-visible:ring-2 focus-visible:ring-amber-500 dark:hover:bg-slate-800"><x-heroicon-o-x-mark class="h-5 w-5" aria-hidden="true" /></button></div>
                <div class="mt-2 space-y-2 text-sm text-slate-600 dark:text-slate-300"><p>{{ $monthLabel }} · B{{ $package->fleet }} · {{ $seat }} · {{ $package->source_revision }}</p><p>{{ count($allLines) }} eligible lines · {{ count($trips) }} imported trips</p>@foreach ($package->payload['coverage'] as $source => $present)<p>{{ ucfirst($source) }}: {{ $present ? 'Imported' : 'Not imported' }}</p>@endforeach</div>
            </section>
        </dialog>
    @endif

    @if ($dirty || $notice)
    <div class="mt-2 flex flex-wrap gap-3 text-sm" role="status" aria-live="polite">
        @if ($dirty)<button type="button" wire:click="retrySave" class="font-semibold underline">Retry save</button><button type="button" wire:click="reloadSaved" wire:confirm="Discard local edits and reload saved choices?" class="underline">Reload saved choices</button>@endif
        @if ($notice)<span wire:key="bid-notice-{{ md5($notice) }}" x-data="{ visible: true }" x-show="visible" x-init="setTimeout(() => visible = false, 8000)">{{ $notice }} @if ($undoId)<button type="button" wire:click="undoAdd" class="font-semibold underline">Undo addition</button>@endif</span>@endif
    </div>
    @endif
    @if ($errors->any())<div role="alert" class="my-3 rounded-lg border border-amber-500 p-3 text-sm text-amber-900 dark:text-amber-200">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <p data-bid-network-error hidden role="alert" class="my-3 rounded-lg border border-amber-500 p-3 text-sm">Request failed. Check your choices and retry the action. <button type="button" wire:click="retrySave" class="min-h-11 underline">Retry saving preferences</button></p>

    @if ($showImport)
        @can('manage-crew-bids')
        <section class="my-4 rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900" aria-label="Manage bid package">
            <h2 class="text-lg font-semibold">Import a bid package</h2>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Lines PDF, Trips PDF, Overview .xlsx. Up to 3 files, 12 MB each. Partial packages identify unavailable details.</p>
            <form wire:submit="previewImport" class="mt-4 flex flex-col items-start gap-4">
                <label class="text-sm">Package files<input type="file" wire:model="files" multiple accept=".pdf,.xlsx" class="mt-1 block max-w-full text-sm"></label>
                <p class="text-xs text-slate-500 dark:text-slate-400">Package dates/times are UTC. Blank Overview date cells represent days off.</p>
                <button type="submit" wire:loading.attr="disabled" class="min-h-11 rounded-lg bg-[#1B365D] px-4 font-semibold text-white disabled:opacity-50">Preview import</button>
            </form>
            @if ($draft)
                <div class="mt-4 border-t border-slate-200 pt-4 dark:border-slate-700">
                    <h3 class="font-semibold">Preview: {{ $draft->period }} · B{{ $draft->fleet }}</h3>
                    <p class="mt-1 text-sm">{{ count($draft->payload['lines']) }} lines · {{ count($draft->payload['trips']) }} trips · {{ $draft->source_revision }}</p>
                    @foreach ($draft->payload['coverage'] as $source => $present)<p class="text-sm">{{ ucfirst($source) }}: {{ $present ? 'Imported' : 'Not imported' }}</p>@endforeach
                    <button type="button" wire:click="publishImport" wire:loading.attr="disabled" class="mt-3 min-h-11 rounded-lg bg-[#1B365D] px-4 font-semibold text-white">Publish shared package</button>
                </div>
            @endif
        </section>
        @endcan
    @endif

    @if ($target)
        <section role="region" aria-label="Review package revision" class="my-4 rounded-xl border border-amber-500 p-4">
            <h2 class="font-semibold">Review revision: {{ $target->source_revision }}</h2>
            <ul class="mt-2 space-y-3 text-sm">@forelse ($revisionChanges as $change)<li><p class="font-semibold">{{ $change['id'] }} — {{ $change['status'] }}</p>@foreach ($change['details'] as $detail)<p class="mt-1">{{ $detail }}</p>@endforeach</li>@empty<li>No selected lines to carry forward.</li>@endforelse</ul>
            <div class="mt-3 flex flex-wrap gap-3"><button type="button" wire:click="confirmRevision" class="min-h-11 rounded-lg bg-[#1B365D] px-4 font-semibold text-white">Confirm and carry choices forward</button><button type="button" wire:click="dismissRevision" class="min-h-11 px-3 underline">Keep current revision</button></div>
        </section>
    @elseif ($latest)
        <p class="mt-3 text-sm">A newer package revision is available. <button type="button" wire:click="reviewRevision({{ $latest->id }})" class="min-h-11 px-2 font-semibold underline">Review changes</button></p>
    @endif

    @if ($package)
        <div class="my-3 flex flex-wrap items-center gap-x-3 gap-y-2 border-b border-slate-200 pb-2 dark:border-slate-700" data-bid-control-toolbar>
        <nav class="flex shrink-0 gap-1" aria-label="Bid package views">
            @foreach (['flying' => 'Lines', 'trips' => 'Trips', 'reserve' => 'Reserve'] as $value => $label)<button type="button" wire:click="$set('tab', '{{ $value }}')" @class(['min-h-11 rounded-lg px-4 text-sm font-semibold focus-visible:ring-2 focus-visible:ring-amber-500', 'bg-[#1B365D] text-white' => $tab === $value, 'text-slate-600 dark:text-slate-300' => $tab !== $value]) aria-current="{{ $tab === $value ? 'page' : 'false' }}">{{ $label }}</button>@endforeach
        </nav>
        @if ($tab !== 'trips')<span class="ms-auto whitespace-nowrap text-xs text-slate-500 dark:text-slate-400" aria-live="polite">{{ count($lines) }} matching / {{ $total }}</span>@endif
        <div @class(['flex max-w-full flex-wrap items-center justify-end gap-2 text-xs', 'ms-auto' => $tab === 'trips'])>
            <button type="button" @click="$wire.showPreferences = ! $wire.showPreferences" :aria-expanded="$wire.showPreferences" aria-controls="bid-preferences" class="inline-flex min-h-11 max-w-40 items-center gap-1.5 rounded-full bg-slate-200/70 px-3 text-slate-700 focus-visible:ring-2 focus-visible:ring-amber-500 dark:bg-slate-800 dark:text-slate-200 sm:max-w-64">
                <x-heroicon-o-adjustments-horizontal class="h-4 w-4 shrink-0" aria-hidden="true" /><span class="sr-only">Edit preferences: </span><span class="truncate">@forelse ($goals as $goal){{ $goalLabels[$goal] ?? $goal }}{{ $loop->last ? '' : ' → ' }}@empty Preferences @endforelse @if ($daysOff !== []) · {{ count($daysOff) }} dates off (UTC)@endif @if ($startCity) · Start {{ $startCity }}@endif</span>
            </button>
            @if ($tab !== 'trips')
                <label for="bid-sort" class="sr-only">Sort lines</label><select id="bid-sort" wire:model.live.debounce.250ms="sort" class="min-h-11 w-32 max-w-full rounded-lg text-xs focus-visible:ring-2 focus-visible:ring-amber-500 dark:bg-slate-900 sm:w-auto"><option value="best">{{ $goals === [] ? 'Line ID (no goals set)' : 'Best match' }}</option><option value="credit">Highest credit</option><option value="block">Highest block time</option><option value="days_off">Most days off</option><option value="id">Line ID</option></select>
                <button type="button" x-ref="filtersTrigger" @click="filtersOpen = ! filtersOpen" :aria-expanded="filtersOpen" aria-controls="bid-filters" class="inline-flex min-h-11 items-center gap-1.5 rounded-full border border-slate-300 px-3 font-semibold focus-visible:ring-2 focus-visible:ring-amber-500 dark:border-slate-600"><x-heroicon-o-funnel class="h-4 w-4" aria-hidden="true" /><span>Filters</span>@if ($layoverCity !== '' || $extendedLayoversOnly || $avoidShortLayovers || $minimumCredit !== null || $minimumDaysOff !== null || $selectedOnly)<span class="h-1.5 w-1.5 rounded-full bg-[#C5A059]" aria-hidden="true"></span><span class="sr-only">active</span>@endif</button>
            @endif
        </div>
        </div>
            <section id="bid-preferences" wire:show="showPreferences" x-cloak class="my-3 rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900" aria-label="Set preferences">
                <h2 class="text-lg font-semibold">Set preferences</h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Choose goals, then order them. Lower priorities only break ties. Days off and start city exclude nonmatching lines.</p>
                <div x-data="bidGoalPreferences(@js($goalLabels))" @bid-goals-changed.debounce.250ms="$wire.$commit()">
                    <fieldset class="mt-3 flex flex-wrap gap-4" @change="$dispatch('bid-goals-changed')"><legend class="mb-2 text-sm font-semibold">1. Choose goals</legend>@foreach ($goalLabels as $value => $label)<label class="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" wire:model="goals" value="{{ $value }}" class="rounded">{{ $label }}</label>@endforeach</fieldset>
                    <ol wire:ignore x-sort="sortGoal($item, $el)" class="mt-2 list-decimal space-y-1 ps-6" aria-label="Goal priority order">
                        <template x-for="(goal, index) in $wire.goals" :key="goal">
                            <li x-sort:item="goal" :data-goal="goal">
                                <div class="flex items-center gap-2 text-sm">
                                    <span x-sort:handle aria-hidden="true" class="cursor-grab">⠿</span>
                                    <span x-text="labels[goal]"></span>
                                    <button type="button" x-show="index > 0" x-cloak @click="moveGoal(goal, index - 1)" x-sort:ignore class="min-h-11 rounded px-2 underline focus-visible:ring-2 focus-visible:ring-amber-500" :aria-label="'Move ' + labels[goal] + ' up'">Move up</button>
                                </div>
                            </li>
                        </template>
                    </ol>
                </div>
                <fieldset class="mt-4 max-w-md" @change.debounce.250ms="$wire.$commit()">
                    <legend class="font-semibold text-sm">2. Days to keep off · UTC · {{ $monthLabel }}</legend>
                    <div class="mt-2 grid grid-cols-7 gap-1">
                        @foreach ($dates as $date)
                            <label class="relative min-w-0 cursor-pointer" wire:key="bid-day-off-{{ $packageId }}-{{ $date['date'] }}">
                                <input type="checkbox" wire:model="daysOff" value="{{ $date['date'] }}" aria-label="{{ $date['date'] }} UTC, {{ $date['weekday'] }}" @checked(in_array($date['date'], $daysOff, true)) class="peer sr-only">
                                <span class="flex min-h-11 flex-col items-center justify-center rounded-lg border border-slate-200 text-sm peer-checked:border-[#1B365D] peer-checked:bg-[#1B365D] peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-amber-500 dark:border-slate-700 dark:peer-checked:border-[#1B365D]"><span class="block text-[10px]">{{ $date['weekday'] }}</span>{{ $date['day'] }}</span>
                            </label>
                        @endforeach
                    </div>
                    <button type="button" wire:click="clearDates" class="min-h-11 text-sm underline">Clear dates</button>
                </fieldset>
                <details class="mt-2 text-sm"><summary class="cursor-pointer py-2 underline">Select a date range</summary><div class="flex flex-wrap items-end gap-2"><label>From (UTC)<input type="date" min="{{ $packageStartDate }}" max="{{ $packageEndDate }}" wire:model="rangeStart" class="block rounded-lg dark:bg-slate-800"></label><label>Through (UTC)<input type="date" min="{{ $packageStartDate }}" max="{{ $packageEndDate }}" wire:model="rangeEnd" class="block rounded-lg dark:bg-slate-800"></label><button type="button" wire:click="addRange" class="min-h-11 px-3 underline">Add range</button></div></details>
                <label class="mt-4 block text-sm font-semibold" for="bid-start-city">3. Required start city</label><select id="bid-start-city" wire:model.live.debounce.250ms="startCity" class="mt-1 rounded-lg text-sm dark:bg-slate-800"><option value="">Any start city</option>@foreach ($cities as $city)<option value="{{ $city }}">{{ $city }}</option>@endforeach</select>
                <div class="mt-4 flex gap-3"><button type="button" wire:click.debounce.250ms="finishSetup" class="min-h-11 rounded-lg bg-[#1B365D] px-4 font-semibold text-white">{{ $initialized ? 'Done' : 'Skip setup and browse' }}</button></div>
            </section>
        @if ($tab === 'trips')
            <section class="mt-4 space-y-3" aria-label="Trips">
                @forelse ($trips as $trip)
                    <details wire:key="trip-{{ $trip['id'] }}" class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"><summary class="cursor-pointer text-sm"><span class="font-mono font-bold">{{ $trip['id'] }} · {{ $trip['start_city'] }}–{{ $trip['end_city'] }}</span> · {{ $trip['start_date'] }}–{{ $trip['end_date'] }} UTC</summary><p class="mt-2 text-sm">@if (filled($trip['report'] ?? null))Report {{ $trip['report'] }} UTC @endif @if (filled($trip['release'] ?? null))Release {{ $trip['release'] }} UTC @endif</p><ul class="mt-2 space-y-1 text-sm">@foreach ($trip['legs'] as $leg)<li class="font-mono">{{ $leg['date'] }} · {{ $leg['origin'] }}–{{ $leg['destination'] }} · {{ $leg['departure'] }}–{{ $leg['arrival'] }} UTC{{ $leg['deadhead'] ? ' · Deadhead' : '' }}</li>@endforeach</ul></details>
                @empty<p class="rounded-xl border border-slate-200 p-5 dark:border-slate-700">Trips file not imported or no supported trip details were found. @can('manage-crew-bids')Use Manage package to import Trips.@else An admin can update this package.@endcan</p>@endforelse
            </section>
        @else
            <div id="bid-filters" x-show="filtersOpen" x-cloak class="mb-3 space-y-2" role="region" aria-label="Line filters">
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <span class="sr-only">Layover quick filters</span>
                    <button type="button" wire:click="$toggle('extendedLayoversOnly')" aria-pressed="{{ $extendedLayoversOnly ? 'true' : 'false' }}" @class(['min-h-11 rounded-full border px-3 font-semibold focus-visible:ring-2 focus-visible:ring-amber-500', 'border-[#C5A059] bg-[#C5A059]/15 text-[#1B365D] dark:text-amber-200' => $extendedLayoversOnly, 'border-slate-300 text-slate-600 dark:border-slate-600 dark:text-slate-300' => ! $extendedLayoversOnly])>24h+ layover</button>
                    <button type="button" wire:click="$toggle('avoidShortLayovers')" aria-pressed="{{ $avoidShortLayovers ? 'true' : 'false' }}" @class(['min-h-11 rounded-full border px-3 font-semibold focus-visible:ring-2 focus-visible:ring-amber-500', 'border-[#C5A059] bg-[#C5A059]/15 text-[#1B365D] dark:text-amber-200' => $avoidShortLayovers, 'border-slate-300 text-slate-600 dark:border-slate-600 dark:text-slate-300' => ! $avoidShortLayovers])>Avoid under 10h</button>
                    <label class="flex items-center gap-2"><span class="sr-only">Layover airport</span><select wire:model.live.debounce.250ms="layoverCity" class="min-h-11 rounded-full font-mono text-xs focus-visible:ring-2 focus-visible:ring-amber-500 dark:bg-slate-900"><option value="">Any layover airport</option>@foreach ($layoverCities as $city)<option value="{{ $city }}">{{ $city }}</option>@endforeach</select></label>
                    @if ($layoverCity !== '' || $extendedLayoversOnly || $avoidShortLayovers)<button type="button" wire:click="clearLayoverFilters" class="min-h-11 rounded-full px-2 underline focus-visible:ring-2 focus-visible:ring-amber-500">Clear layovers</button>@endif
                    <label class="flex items-center gap-2">Min credit (h)<input type="number" min="0" max="300" wire:model.live.debounce.400ms="minimumCredit" class="w-20 rounded-lg text-xs focus-visible:ring-2 focus-visible:ring-amber-500 dark:bg-slate-900"></label>
                    <label class="flex items-center gap-2">Min days off<input type="number" min="0" max="31" wire:model.live.debounce.400ms="minimumDaysOff" class="w-16 rounded-lg text-xs focus-visible:ring-2 focus-visible:ring-amber-500 dark:bg-slate-900"></label>
                    <label class="flex min-h-11 items-center gap-2"><input type="checkbox" wire:model.live.debounce.250ms="selectedOnly" class="rounded focus-visible:ring-2 focus-visible:ring-amber-500">Selected only</label>
                    <button type="button" wire:click="resetFilters" class="min-h-11 rounded-lg px-2 underline focus-visible:ring-2 focus-visible:ring-amber-500">Reset filters</button>
                </div>
                @error('layoverCity')<p class="mt-1 text-xs text-amber-800 dark:text-amber-300">{{ $message }}</p>@enderror
                @if ($layoverCity !== '' || $extendedLayoversOnly || $avoidShortLayovers)
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Matching layovers are outlined in gold in the trip timeline. Avoid under 10h applies to every airport and requires complete connection data. Your bid list keeps its order.</p>
                @elseif ($layoverCities === [])
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">No connected layover data is available for these lines.</p>
                @endif
            </div>
            <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">Credit h:mm[:ss] · Block h:mm · UTC · Blue: flying trip (includes layover/rest) · Orange: reserve · Light: off · ?: unknown</p>
            <section class="space-y-3" aria-label="Available lines">
                @forelse ($lines as $line)<x-crew-bids.line :line="$line" :rank="in_array($line['id'], $selections, true) ? array_search($line['id'], $selections, true) + 1 : null" :show-blocks="in_array('blocks', $goals, true)" wire:key="bid-line-{{ $packageId }}-{{ $line['id'] }}" />
                @empty<div class="rounded-xl border border-slate-200 p-5 dark:border-slate-700"><p>No lines match these filters.</p><button type="button" wire:click="resetFilters" class="min-h-11 font-semibold underline">Reset filters</button></div>@endforelse
            </section>
        @endif
    @else
        <section class="my-8 rounded-xl border border-slate-200 bg-white p-8 text-center dark:border-slate-700 dark:bg-slate-900"><h2 class="text-lg font-semibold">No bid package available yet</h2><p class="mt-2 text-sm text-slate-500 dark:text-slate-400">@can('manage-crew-bids')Open Manage package to import and publish a bid package.@else An admin will publish packages here when available.@endcan</p></section>
    @endif

    @if ($showList)
        <div class="fixed inset-0 z-50 bg-black/40" x-data x-trap.inert.noscroll="true" x-init="$nextTick(() => $refs.closeList.focus())">
            <section role="dialog" aria-modal="true" aria-labelledby="bid-list-title" class="absolute inset-0 flex flex-col bg-white dark:bg-slate-950 sm:start-auto sm:w-full sm:max-w-xl">
                <header class="flex items-center justify-between border-b border-slate-200 p-4 dark:border-slate-700"><h2 id="bid-list-title" class="text-lg font-semibold">{{ $showReview ? 'Review bid list' : 'My bid list' }} ({{ count($selections) }})</h2><button x-ref="closeList" type="button" wire:click="$set('showList', false)" @click="$nextTick(() => document.querySelector('[data-crew-bids] [x-ref=listTrigger]')?.focus())" class="min-h-11 rounded-lg px-2 underline">Back to lines</button></header>
                <div class="flex-1 space-y-3 overflow-y-auto p-4">
                    @if ($package)<p class="text-sm">{{ $monthLabel }} · B{{ $package->fleet }} · {{ $seat }}</p>@endif
                    @if ($showReview)
                        <p class="text-sm text-slate-500 dark:text-slate-400">Review your order and warnings. Copying does not submit your bid.</p>
                        <textarea id="bid-copy-text" readonly rows="8" aria-label="Numbered bid list for copying" class="w-full rounded-lg font-mono text-sm dark:bg-slate-900">{{ $copyText }}</textarea>
                    @endif
                    <ol wire:sort="move" class="space-y-2">@forelse ($selected as $index => $line)<li wire:key="rank-{{ $packageId }}-{{ $line['id'] }}" wire:sort:item="{{ $line['id'] }}" class="rounded-lg border border-slate-200 p-3 dark:border-slate-700"><div class="flex flex-wrap items-center gap-2 text-sm"><span wire:sort:handle aria-hidden="true">⠿</span><span class="font-mono font-semibold">{{ $index + 1 }}. {{ $line['id'] }}</span>@if ($line['credit_label'] !== 'Not available')<span>Credit {{ $line['credit_label'] }}</span>@endif @if ($line['block_label'] !== 'Not available')<span>Block {{ $line['block_label'] }}</span>@endif @if (isset($line['days_off']))<span>Days off {{ $line['days_off'] }}</span>@endif<details class="ms-auto"><summary class="cursor-pointer py-2 underline">Move</summary><div class="flex flex-wrap gap-2">@if ($index > 0)<button type="button" wire:click="move('{{ $line['id'] }}', {{ $index - 1 }})" class="min-h-11 underline" aria-label="Move {{ $line['id'] }} up">Up</button>@endif @if ($index < count($selected) - 1)<button type="button" wire:click="move('{{ $line['id'] }}', {{ $index + 1 }})" class="min-h-11 underline" aria-label="Move {{ $line['id'] }} down">Down</button>@endif<label class="text-xs">Position<select wire:change="move('{{ $line['id'] }}', Number($event.target.value))" class="rounded-lg text-sm dark:bg-slate-800">@foreach ($selected as $position => $choice)<option value="{{ $position }}" @selected($position === $index)>{{ $position + 1 }}</option>@endforeach</select></label></div></details><button type="button" wire:click="remove('{{ $line['id'] }}')" aria-label="Remove {{ $line['id'] }}" class="min-h-11 px-2 underline">Remove</button></div>@foreach ($line['warnings'] as $warning)<p class="mt-2 text-sm text-amber-800 dark:text-amber-300">{{ $warning }}</p>@endforeach</li>@empty<li class="py-8 text-sm">Your bid list is empty. Return to lines and select Add to start.</li>@endforelse</ol>
                    <details class="text-sm"><summary class="cursor-pointer py-3 underline">More actions</summary><div class="flex flex-wrap gap-3"><button type="button" wire:click="clearList" wire:confirm="Clear all choices from this bid list?" class="min-h-11 underline">Clear bid list</button><button type="button" wire:click="addMatching" class="min-h-11 underline">Add all {{ count($lines) }} matching {{ $tab === 'reserve' ? 'reserve' : 'flying' }} lines</button></div></details>
                </div>
                <footer class="border-t border-slate-200 p-4 dark:border-slate-700"><p role="status" class="mb-2 text-sm">{{ $dirty ? 'Unsaved changes — Retry save before copying' : ($revision > 0 ? 'Saved' : '') }}</p>@if ($dirty)<button type="button" wire:click="retrySave" class="min-h-11 px-3 underline">Retry save</button>@elseif ($showReview)<button type="button" data-bid-copy class="min-h-11 rounded-lg bg-[#1B365D] px-4 font-semibold text-white" @disabled($selections === [])>Copy numbered list</button><span data-bid-copy-status role="status" class="ms-2 text-sm"></span>@else<button type="button" wire:click="$set('showReview', true)" class="min-h-11 rounded-lg bg-[#1B365D] px-4 font-semibold text-white" @disabled($selections === [])>Review / Copy</button>@endif</footer>
            </section>
        </div>
    @endif
</div>
