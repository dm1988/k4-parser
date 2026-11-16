<div class="mx-auto max-w-7xl px-3 py-6 sm:px-6" data-crew-bids data-dirty="{{ $dirty ? 'true' : 'false' }}" x-data="crewBidsHeader" @bid-action-failed="$refs.networkError.hidden = false; if ($refs.preferencesNetworkError) $refs.preferencesNetworkError.hidden = false" :data-preferences-dirty="$wire.$dirty(['goals', 'daysOff', 'startCity'])" @keydown.escape.window="if (statusOpen) { $event.preventDefault(); closeStatus(true) } else if ($wire.showPreferences) { $wire.showPreferences = false } else if ($wire.showList) { $event.preventDefault(); closeList() } else if (filtersOpen) { filtersOpen = false; $refs.filtersTrigger?.focus() }">
    <header x-ref="workspaceHeader" class="sticky top-0 z-30 -mx-3 border-b border-slate-200 bg-gray-100 px-3 shadow-sm dark:border-slate-800 dark:bg-gray-950 sm:-mx-6 sm:px-6">
        <div class="flex flex-wrap items-center gap-3 py-3" data-bid-command-bar>
            <div class="flex min-w-0 basis-full flex-wrap items-center gap-3 lg:flex-1 lg:basis-0" data-bid-context>
                <h1 class="shrink-0 text-2xl font-bold text-[#1B365D] dark:text-amber-200">K4 Bids</h1>
                @if ($packages->isNotEmpty())
                    <div class="flex min-w-0 max-w-full items-center gap-1">
                        <div class="flex min-w-0 items-center rounded-full border border-slate-300 bg-white pe-2 dark:border-slate-700 dark:bg-slate-900">
                            <label class="sr-only" for="bid-package">Bid package</label>
                            <select id="bid-package" wire:model="contextPackage" wire:change="changeContext" wire:loading.attr="disabled" wire:target="changeContext" class="min-h-11 min-w-0 max-w-full flex-1 rounded-full border-0 bg-transparent font-mono text-xs focus-visible:ring-2 focus-visible:ring-amber-500 dark:bg-slate-900 sm:text-sm">
                                @foreach ($packages as $option)<option value="{{ $option->id }}">{{ $option->period }} · B{{ $option->fleet }} · {{ $option->source_revision }}</option>@endforeach
                            </select>
                            @if ($package)<span class="shrink-0 rounded-full bg-slate-100 px-2 py-1 text-[10px] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $package->archived_at ? 'Archived' : 'Published' }}</span>@endif
                            @can('manage-crew-bids')<a href="{{ route('filament.admin.resources.bid-packages.index') }}" aria-label="Manage package" title="Manage package" class="inline-flex min-h-11 min-w-11 shrink-0 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100 focus-visible:ring-2 focus-visible:ring-amber-500 dark:text-slate-400 dark:hover:bg-slate-800"><x-heroicon-o-cog-6-tooth class="h-4 w-4" aria-hidden="true" /></a>@endcan
                        </div>
                        @if ($package)<button type="button" x-ref="packageDetailsTrigger" @click="$refs.packageDetails.showModal()" aria-label="Open package details" aria-haspopup="dialog" aria-controls="bid-package-details" title="Package details" class="inline-flex min-h-11 min-w-11 shrink-0 items-center justify-center rounded-full text-slate-500 hover:bg-slate-200 focus-visible:ring-2 focus-visible:ring-amber-500 dark:text-slate-400 dark:hover:bg-slate-800"><x-heroicon-o-information-circle class="h-5 w-5" aria-hidden="true" /></button>@endif
                    </div>
                @else
                    @can('manage-crew-bids')<a href="{{ route('filament.admin.resources.bid-packages.index') }}" class="inline-flex min-h-11 items-center gap-2 rounded-full border border-slate-300 px-3 text-sm focus-visible:ring-2 focus-visible:ring-amber-500 dark:border-slate-600"><x-heroicon-o-cog-6-tooth class="h-4 w-4 shrink-0" aria-hidden="true" /><span>Manage package</span></a>@endcan
                @endif
                @if ($package)
                    <button type="button" x-ref="predictionsTrigger" @click="$refs.predictions.showModal()" wire:poll.60s.visible aria-haspopup="dialog" aria-controls="bid-predictions" title="Bid predictions{{ $prediction['stale'] ? ' · Stale shared bids; refresh recommended' : '' }}" class="inline-flex min-h-11 max-w-full items-center gap-1.5 rounded-full border border-slate-300 bg-white px-2.5 text-xs font-medium text-[#1B365D] hover:bg-slate-50 focus-visible:ring-2 focus-visible:ring-amber-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">
                        <x-heroicon-o-chart-bar class="h-4 w-4 shrink-0 text-[#C5A059]" aria-hidden="true" />
                        <span class="sr-only">Bid predictions: </span>
                        <span class="whitespace-nowrap">@if (! $prediction['available'])Unavailable @elseif ($prediction['likely'])<span class="font-mono font-semibold">{{ $prediction['likely'] }}</span> · Likely hold @else No hold @endif</span>
                        @if ($prediction['stale'])<span class="h-1.5 w-1.5 shrink-0 rounded-full bg-amber-500 dark:bg-amber-400" aria-hidden="true"></span><span class="sr-only"> · Stale shared bids; refresh recommended.</span>@endif
                    </button>
                @endif
            </div>
            @if ($packages->isNotEmpty())
                <a href="{{ route('profile.edit') }}#k4-bids-preferences" title="Change bid seat in user preferences" class="inline-flex min-h-11 items-center rounded-lg px-2 text-sm text-slate-600 underline decoration-slate-300 underline-offset-4 focus-visible:ring-2 focus-visible:ring-amber-500 dark:text-slate-300 dark:decoration-slate-600">{{ $seat === 'CA' ? 'Captain' : 'First Officer' }}</a>
            @endif
            <div class="ms-auto flex max-w-full flex-wrap items-center justify-end gap-3" data-bid-list-actions>
                @if ($notice)<span wire:key="bid-notice-{{ md5($notice) }}" x-data="{ visible: true }" x-show="visible" x-init="setTimeout(() => visible = false, 8000)" class="min-w-0 text-xs text-slate-600 dark:text-slate-300" role="status" aria-live="polite">{{ $notice }} @if ($undoId)<button type="button" wire:click="undoAdd" class="min-h-11 font-semibold underline focus-visible:ring-2 focus-visible:ring-amber-500" wire:loading.attr="disabled" wire:target="undoAdd"><span wire:loading.remove wire:target="undoAdd">Undo addition</span><span wire:loading wire:target="undoAdd" role="status">Undoing…</span></button>@endif</span>@endif
                <div class="relative inline-flex min-w-0 max-w-full rounded-xl bg-[#1B365D] text-white dark:bg-slate-800" data-bid-split-control @click.outside="closeStatus()" @focusout="if (! $el.contains($event.relatedTarget)) closeStatus()">
                    <button type="button" x-ref="listTrigger" @click="openList($event)" title="Open my bid list" :aria-expanded="$wire.showList" aria-controls="bid-list-drawer" class="inline-flex min-h-11 min-w-0 items-center gap-2 rounded-s-xl px-3 py-2 text-xs font-semibold hover:bg-white/10 focus-visible:z-10 focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-amber-400 sm:text-sm">
                        <x-heroicon-o-shopping-cart class="h-5 w-5 shrink-0" aria-hidden="true" />
                        <span class="truncate">My bid list ({{ count($selections) }})</span>
                    </button>
                    <button type="button" x-ref="statusTrigger" @click="toggleStatus()" title="View local save and Kalitta report details" aria-label="View bid list save and submission status" aria-haspopup="dialog" aria-describedby="bid-save-status bid-submission-status" :aria-expanded="statusOpen" aria-controls="bid-status-popover" class="inline-flex min-h-11 min-w-11 shrink-0 items-center justify-center gap-2 rounded-e-xl border-s border-white/20 px-2 py-1.5 hover:bg-white/10 focus-visible:z-10 focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-amber-400 sm:px-3">
                        <span class="relative inline-flex h-6 w-6 shrink-0" :class="{ 'text-emerald-400': cloudState(@js($submissionStatus->state)) === 'synced', 'text-amber-300': cloudState(@js($submissionStatus->state)) === 'pending', 'text-slate-300': cloudState(@js($submissionStatus->state)) === 'unconfirmed' }" aria-hidden="true">
                            <span x-show="cloudState(@js($submissionStatus->state)) !== 'pending'"><x-heroicon-o-cloud class="h-6 w-6" /></span>
                            <span x-cloak x-show="cloudState(@js($submissionStatus->state)) === 'pending'"><x-heroicon-o-cloud-arrow-up class="h-6 w-6" /></span>
                            <span x-cloak x-show="cloudState(@js($submissionStatus->state)) === 'synced'" class="absolute -bottom-0.5 -end-0.5 inline-flex h-3 w-3 items-center justify-center rounded-full bg-emerald-400 text-slate-950 ring-2 ring-[#1B365D] dark:ring-slate-800"><x-heroicon-o-check class="h-2.5 w-2.5" /></span>
                            <span x-cloak x-show="cloudState(@js($submissionStatus->state)) === 'pending'" class="absolute bottom-0 end-0 h-2 w-2 rounded-full bg-amber-300 ring-2 ring-[#1B365D] dark:ring-slate-800" :class="{ 'animate-pulse motion-reduce:animate-none': saveState() === 'saving' || saveState() === 'unsaved' }"></span>
                        </span>
                        <span class="hidden flex-col items-start gap-1 text-[10px] font-medium leading-4 sm:flex">
                            <span class="inline-flex items-center rounded-full px-2" :title="'Local save: ' + saveDescription()" :class="saveState() === 'saved' ? 'bg-emerald-400/15 text-emerald-300' : (saveState() === 'empty' ? 'bg-slate-500/20 text-slate-300' : 'bg-amber-400/15 text-amber-300')">
                                <span x-text="saveBadgeLabel()">{{ $dirty ? 'Unsaved' : ($lastSavedAt ? 'Saved' : 'Not saved') }}</span>
                            </span>
                            <span title="{{ $submissionStatus->description }}" @class(['inline-flex items-center rounded-full px-2', 'bg-amber-400/15 text-amber-300' => $submissionStatus->state === 'changed', 'bg-slate-500/20 text-slate-300' => $submissionStatus->state !== 'changed'])>
                                <span>{{ $submissionStatus->state === 'matched' ? 'Uploaded' : ($submissionStatus->state === 'changed' ? 'Upload differs' : 'Unconfirmed') }}</span>
                            </span>
                        </span>
                        <x-heroicon-o-chevron-down class="hidden h-3 w-3 shrink-0 sm:block" aria-hidden="true" />
                    </button>
                    <span id="bid-save-status" class="sr-only" role="status" aria-live="polite" x-text="'Local save: ' + saveDescription() + '. ' + saveHelp()"></span>
                    <span id="bid-submission-status" class="sr-only" role="status" aria-live="polite">{{ $submissionStatus->description }}</span>
                    <section id="bid-status-popover" x-ref="statusPanel" x-show="statusOpen" x-cloak role="dialog" aria-labelledby="bid-status-title" tabindex="-1" class="absolute end-0 top-full z-50 mt-2 max-h-[70dvh] w-80 max-w-[calc(100vw-1.5rem)] overflow-y-auto rounded-xl border border-slate-200 bg-white p-4 text-start text-slate-900 shadow-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
                        <div class="flex items-center justify-between gap-3"><h2 id="bid-status-title" class="text-sm font-semibold">Bid list status</h2><button type="button" @click="closeStatus(true)" aria-label="Close bid list status" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg hover:bg-slate-100 focus-visible:ring-2 focus-visible:ring-amber-500 dark:hover:bg-slate-800"><x-heroicon-o-x-mark class="h-4 w-4" aria-hidden="true" /></button></div>
                        <div class="space-y-1 text-xs">
                            <p class="inline-flex rounded-full px-2 py-0.5 font-semibold" :class="saveState() === 'saved' ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-400/15 dark:text-emerald-300' : (saveState() === 'empty' ? 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300' : 'bg-amber-50 text-amber-800 dark:bg-amber-400/15 dark:text-amber-300')" x-text="saveLabel()">Local workspace</p>
                            <p class="text-slate-600 dark:text-slate-300" x-text="saveDescription()"></p>
                            <p x-show="savedTimestamp()" x-cloak class="font-mono text-slate-600 dark:text-slate-300">Last local save: <time :datetime="$wire.lastSavedAt" x-text="savedTimestamp()"></time></p>
                            <p class="text-slate-600 dark:text-slate-300" x-text="saveHelp()"></p>
                        </div>
                        <div class="mt-3 space-y-1 border-t border-slate-200 pt-3 text-xs dark:border-slate-700">
                            <p @class(['inline-flex rounded-full px-2 py-0.5 font-semibold', 'bg-amber-50 text-amber-800 dark:bg-amber-400/15 dark:text-amber-300' => $submissionStatus->state === 'changed', 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300' => $submissionStatus->state !== 'changed'])>{{ $submissionStatus->label }}</p>
                            <p class="text-slate-600 dark:text-slate-300">{{ $submissionStatus->description }}</p>
                        </div>
                    </section>
                </div>
            </div>
        </div>
        @if ($package)
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-slate-200 py-2 dark:border-slate-800" data-bid-control-toolbar>
                <div class="flex shrink-0 items-center gap-2 text-xs">
                    <span class="whitespace-nowrap font-mono text-slate-500 dark:text-slate-400" aria-live="polite">@if ($tab === 'trips'){{ count($trips) }} trips @else{{ count($lines) }} matching / {{ $total }}@endif</span>
                    <label for="bid-view" class="sr-only">Bid package view</label>
                    <select id="bid-view" wire:model.change.live="tab" wire:loading.attr="disabled" wire:target="tab" class="min-h-11 w-28 rounded-lg border-slate-300 bg-white py-2 ps-3 pe-8 text-xs font-medium text-slate-600 focus-visible:ring-2 focus-visible:ring-amber-500 disabled:opacity-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300">
                        <option value="flying">Lines</option>
                        <option value="trips">Trips</option>
                        <option value="reserve">Reserve</option>
                    </select>
                </div>
                <div class="ms-auto flex min-w-0 max-w-full flex-wrap items-center justify-end gap-2 text-xs">
                    <div class="flex min-w-0 max-w-full items-center gap-1 rounded-xl border border-slate-300 bg-white p-1 dark:border-slate-700 dark:bg-slate-900">
                        <button type="button" x-ref="preferencesTrigger" @click="$wire.showPreferences = true" :aria-expanded="$wire.showPreferences" aria-haspopup="dialog" aria-controls="bid-preferences" aria-describedby="bid-ranking-status" :title="$wire.tab === 'trips' ? 'Ranking preferences inactive in Trips view.' : ($wire.sort === 'best' ? 'Edit preferences for Best match.' : 'Ranking preferences inactive for this sort. Dates off and start city filters still apply.')" class="inline-flex min-h-11 min-w-0 max-w-80 items-center gap-1.5 rounded-full px-3 focus-visible:ring-2 focus-visible:ring-amber-500" :class="$wire.sort === 'best' && $wire.tab !== 'trips' ? 'bg-[#C5A059]/20 text-[#1B365D] dark:text-amber-200' : 'bg-slate-200/70 text-slate-700 dark:bg-slate-800 dark:text-slate-200'">
                            <span class="inline-flex min-w-0 items-center gap-1.5" :class="{ 'opacity-50': $wire.sort !== 'best' || $wire.tab === 'trips' }">
                                <x-heroicon-o-adjustments-horizontal class="h-4 w-4 shrink-0" aria-hidden="true" /><span class="sr-only">Edit preferences: </span><span class="truncate"><span :class="{ 'line-through': $wire.sort !== 'best' || $wire.tab === 'trips' }">@forelse ($goals as $goal){{ $goalLabels[$goal] ?? $goal }}{{ $loop->last ? '' : ' → ' }}@empty Preferences @endforelse</span> @if ($daysOff !== []) · {{ count($daysOff) }} dates off (UTC)@endif @if ($startCity) · Start {{ $startCity }}@endif</span>
                            </span>
                        </button>
                        <span id="bid-ranking-status" class="sr-only" role="status">@if ($tab === 'trips')Ranking preferences inactive in Trips view.@elseif ($sort !== 'best')Ranking preferences inactive for this sort. Dates off and start city filters still apply.@else Best match uses your selected ranking goals.@endif</span>
                        @if ($tab !== 'trips')
                            <label for="bid-sort" class="sr-only">Sort lines</label>
                            <select id="bid-sort" wire:model.change.live="sort" class="min-h-11 w-32 max-w-64 shrink-0 rounded-lg border-0 bg-transparent text-xs focus-visible:ring-2 focus-visible:ring-amber-500 dark:bg-slate-900 sm:w-auto">
                                @foreach (\App\Enums\CrewBidSort::cases() as $option)
                                    <option value="{{ $option->value }}" @disabled($option === \App\Enums\CrewBidSort::Submitted && ! $submittedOrder->available())>{{ $option === \App\Enums\CrewBidSort::BestMatch && $goals === [] ? 'Line ID (no goals set)' : $option->label() }}{{ $option === \App\Enums\CrewBidSort::Submitted && ! $submittedOrder->available() ? ' — '.$submittedOrder->unavailableReason : '' }}</option>
                                @endforeach
                            </select>
                        @endif
                    </div>
                    @if ($tab !== 'trips')
                        <button type="button" x-ref="filtersTrigger" @click="filtersOpen = ! filtersOpen" :aria-expanded="filtersOpen" aria-controls="bid-filters" class="inline-flex min-h-11 items-center gap-1.5 rounded-full border border-slate-300 px-3 font-semibold focus-visible:ring-2 focus-visible:ring-amber-500 dark:border-slate-600"><x-heroicon-o-funnel class="h-4 w-4" aria-hidden="true" /><span>Filters</span><span class="rounded-full bg-[#C5A059]/15 px-1.5 font-mono">{{ $activeFilterCount }}<span class="sr-only"> active browsing filters</span></span></button>
                    @endif
                </div>
            </div>
            @if ($sort === \App\Enums\CrewBidSort::Submitted->value && $submittedOrder->available())
                <p class="pb-2 text-xs text-slate-500 dark:text-slate-400" role="status">Submitted order · Kalitta report checked <time datetime="{{ $submittedOrder->checkedAt->toIso8601String() }}">{{ $submittedOrder->checkedAt->utc()->format('Y-m-d H:i:s') }} UTC</time>. This is the last order checked by the app.</p>
            @endif
        @endif
    </header>

    @if ($package)
        <dialog id="bid-package-details" x-ref="packageDetails" wire:ignore.self aria-labelledby="bid-package-details-title" @keydown.escape.stop @click="if ($event.target === $el) $el.close()" @close="$refs.packageDetailsTrigger.focus()" class="fixed inset-0 m-auto max-h-[80dvh] w-[calc(100%_-_2rem)] max-w-lg overflow-y-auto rounded-xl border border-slate-200 bg-white p-0 text-slate-900 shadow-xl backdrop:bg-slate-950/50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
            <section class="p-5">
                <div class="flex items-center justify-between gap-3"><h2 id="bid-package-details-title" class="text-lg font-semibold">Package details</h2><button type="button" autofocus @click="$refs.packageDetails.close()" aria-label="Close package details" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-full hover:bg-slate-100 focus-visible:ring-2 focus-visible:ring-amber-500 dark:hover:bg-slate-800"><x-heroicon-o-x-mark class="h-5 w-5" aria-hidden="true" /></button></div>
                <div class="mt-2 space-y-2 text-sm text-slate-600 dark:text-slate-300"><p>{{ $monthLabel }} · B{{ $package->fleet }} · {{ $seat }} · {{ $package->source_revision }}</p><p>{{ count($allLines) }} eligible lines · {{ count($trips) }} imported trips</p>@foreach ($package->payload['coverage'] as $source => $present)<p>{{ ucfirst($source) }}: {{ $present ? 'Imported' : 'Not imported' }}</p>@endforeach</div>
                <a href="{{ route('crew-bids.employees.index', ['package' => $package, 'seat' => $seat]) }}" class="mt-4 inline-flex min-h-11 items-center gap-2 rounded-lg border border-slate-300 px-3 text-sm font-semibold hover:bg-slate-100 focus-visible:ring-2 focus-visible:ring-amber-500 dark:border-slate-700 dark:hover:bg-slate-800"><x-heroicon-o-users class="h-4 w-4" aria-hidden="true" />My predictions / shared data</a>
            </section>
        </dialog>
    @endif

    <x-crew-bids.pay-editor :editor="$payEditor" />

    @if ($package)
        <dialog id="bid-predictions" x-ref="predictions" wire:ignore.self aria-labelledby="bid-predictions-title" aria-describedby="bid-predictions-reason" @keydown.escape.stop @click="if ($event.target === $el) $el.close()" @close="$refs.predictionsTrigger.focus()" class="fixed inset-0 m-auto max-h-[80dvh] w-[calc(100%_-_2rem)] max-w-2xl overflow-y-auto rounded-xl border border-slate-200 bg-white p-0 text-slate-900 shadow-xl backdrop:bg-slate-950/50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
            <section class="space-y-4 p-5">
                <div class="flex items-center justify-between gap-3">
                    <h2 id="bid-predictions-title" class="text-lg font-semibold">Bid predictions</h2>
                    <button type="button" autofocus @click="$refs.predictions.close()" aria-label="Close bid predictions" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-full hover:bg-slate-100 focus-visible:ring-2 focus-visible:ring-amber-500 dark:hover:bg-slate-800"><x-heroicon-o-x-mark class="h-5 w-5" aria-hidden="true" /></button>
                </div>
                <p class="text-sm font-semibold">{{ $monthLabel }} · B{{ $package->fleet }} · {{ $seat }}</p>
                @if ($prediction['likely'])<p class="font-semibold text-amber-800 dark:text-amber-200">Likely hold <span class="font-mono">{{ $prediction['likely'] }}</span></p>@endif
                <p id="bid-predictions-reason" class="text-sm">{{ $prediction['reason'] }}</p>
                @if ($prediction['employee_seniority'] !== null)<p class="font-mono text-xs">Your seniority: {{ $prediction['employee_seniority'] }}</p>@endif
                @if ($prediction['checked_at'])<p class="font-mono text-xs">Shared bids: {{ $prediction['checked_at'] }}{{ $prediction['stale'] ? ' · Stale—refresh recommended' : '' }}</p>@endif
                @if ($prediction['seniority_date'])<p class="font-mono text-xs">Seniority list: {{ $prediction['seniority_date'] }}</p>@endif
                @if ($prediction['available'])
                    <ul class="flex flex-wrap gap-x-4 gap-y-2 text-xs" aria-label="Prediction color key">
                        <li class="flex items-center gap-1.5"><x-heroicon-o-check-circle class="h-4 w-4 text-emerald-700 dark:text-emerald-400" aria-hidden="true" />Green: positions remain</li>
                        <li class="flex items-center gap-1.5"><x-heroicon-o-exclamation-circle class="h-4 w-4 text-amber-700 dark:text-amber-300" aria-hidden="true" />Amber: likely hold or one position of multiple remains</li>
                        <li class="flex items-center gap-1.5"><x-heroicon-o-no-symbol class="h-4 w-4 text-slate-600 dark:text-slate-400" aria-hidden="true" />Slate: no positions remain</li>
                    </ul>
                    <div class="overflow-x-auto rounded-lg border border-slate-200 focus-visible:ring-2 focus-visible:ring-amber-500 dark:border-slate-700" tabindex="0" role="region" aria-label="Senior projection breakdown">
                        <table class="w-full text-left text-xs">
                            <caption class="p-3 text-left font-semibold">Remaining {{ $seat }} positions after senior projections · all package lines</caption>
                            <thead class="bg-slate-50 dark:bg-slate-800"><tr><th scope="col" class="px-3 py-2">Line</th><th scope="col" class="px-3 py-2">Projection</th><th scope="col" class="px-3 py-2">Remaining / capacity</th><th scope="col" class="px-3 py-2">Senior holds</th></tr></thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                                @foreach ($prediction['lines'] as $id => $projection)
                                    <tr><th scope="row" class="px-3 py-2 font-mono">{{ $id }}</th><td class="px-3 py-2">{{ $projection['label'] }}</td><td class="px-3 py-2 font-mono">{{ $projection['remaining'] !== null ? $projection['remaining'].' / '.$projection['capacity'] : 'Unknown' }}</td><td class="px-3 py-2 font-mono">{{ $projection['remaining'] !== null ? count($projection['occupants']) : 'Unknown' }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                <div class="flex flex-wrap items-end gap-3">
                    <a href="{{ route('crew-bids.employees.index', ['package' => $package, 'seat' => $seat]) }}" class="inline-flex min-h-11 items-center rounded-lg border border-slate-300 px-3 text-sm font-semibold focus-visible:ring-2 focus-visible:ring-amber-500 dark:border-slate-700">Employee link / shared data</a>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $prediction['basis'] }}. Projections use your verified employee number, the dated seniority list and {{ $seat }} capacity. These are estimates, not official awards.</p>
            </section>
        </dialog>
    @endif

    @if ($dirty)
    <div class="mt-2 flex flex-wrap gap-3 text-sm" role="status" aria-live="polite">
        <button type="button" wire:click="retrySave" class="font-semibold underline" wire:loading.attr="disabled" wire:target="retrySave"><span wire:loading.remove wire:target="retrySave">Retry save</span><span wire:loading wire:target="retrySave" role="status">Saving…</span></button><button type="button" wire:click="reloadSaved" wire:confirm="Discard local edits and reload saved choices?" class="underline" wire:loading.attr="disabled" wire:target="reloadSaved"><span wire:loading.remove wire:target="reloadSaved">Reload saved choices</span><span wire:loading wire:target="reloadSaved" role="status">Reloading…</span></button>
    </div>
    @endif
    @if ($errors->any())<div role="alert" class="my-3 rounded-lg border border-amber-500 p-3 text-sm text-amber-900 dark:text-amber-200">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <p data-bid-network-error x-ref="networkError" hidden role="alert" class="my-3 rounded-lg border border-amber-500 p-3 text-sm">Request failed. Check your choices and retry the action. <button type="button" wire:click="retrySave" class="min-h-11 underline" wire:loading.attr="disabled" wire:target="retrySave"><span wire:loading.remove wire:target="retrySave">Retry saving preferences</span><span wire:loading wire:target="retrySave" role="status">Saving…</span></button></p>

    @can('manage-crew-bids')
        <section id="bid-package-import" wire:show="showImport" x-cloak class="my-4 rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900" aria-label="Manage bid package">
            <h2 class="text-lg font-semibold">Import a bid package</h2>
            <form wire:submit="importSeniority" class="mt-4 space-y-3 border-b border-slate-200 pb-4 dark:border-slate-700">
                <label for="seniority-url" class="block text-sm font-semibold">Update pilot seniority list</label>
                <input id="seniority-url" type="url" wire:model="seniorityUrl" required maxlength="2048" placeholder="https://crewbids.kalittaair.com/hyperlinks/…pdf" class="block w-full rounded-lg text-sm dark:bg-slate-950">
                <p class="text-xs text-slate-500 dark:text-slate-400">Manual updates only. Import the complete dated PDF using its direct Kalitta link. Employee links are preserved.</p>
                <button type="submit" wire:loading.attr="disabled" wire:target="importSeniority" class="min-h-11 rounded-lg bg-[#1B365D] px-4 text-sm font-semibold text-white focus-visible:ring-2 focus-visible:ring-amber-500 disabled:opacity-50"><span wire:loading.remove wire:target="importSeniority">Import seniority PDF</span><span wire:loading wire:target="importSeniority" role="status">Importing seniority…</span></button>
                @error('seniorityUrl')<p role="alert" class="text-sm text-amber-800 dark:text-amber-200">{{ $message }}</p>@enderror
            </form>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Lines PDF, Trips PDF, Overview .xlsx. Up to 3 files, 12 MB each. Partial packages identify unavailable details.</p>
            <form wire:submit="previewImport" class="mt-4 flex flex-col items-start gap-4">
                <label class="text-sm">Package files<input type="file" wire:model="files" wire:loading.attr="disabled" wire:target="previewImport,publishImport" multiple accept=".pdf,.xlsx" class="mt-1 block max-w-full text-sm"></label>
                <p wire:loading wire:target="files" role="status" class="text-xs text-slate-500 dark:text-slate-400">Uploading package files…</p>
                <p class="text-xs text-slate-500 dark:text-slate-400">Package dates/times are UTC. Blank Overview date cells represent days off.</p>
                <button type="submit" wire:loading.attr="disabled" wire:target="files,previewImport,publishImport" class="min-h-11 rounded-lg bg-[#1B365D] px-4 font-semibold text-white disabled:opacity-50"><span wire:loading.remove wire:target="previewImport">Preview import</span><span wire:loading wire:target="previewImport" role="status">Preparing preview…</span></button>
            </form>
            @if ($draft)
                <div class="mt-4 border-t border-slate-200 pt-4 dark:border-slate-700">
                    <h3 class="font-semibold">Preview: {{ $draft->period }} · B{{ $draft->fleet }}</h3>
                    <p class="mt-1 text-sm">{{ count($draft->payload['lines']) }} lines · {{ count($draft->payload['trips']) }} trips · {{ $draft->source_revision }}</p>
                    @foreach ($draft->payload['coverage'] as $source => $present)<p class="text-sm">{{ ucfirst($source) }}: {{ $present ? 'Imported' : 'Not imported' }}</p>@endforeach
                    <button type="button" wire:click="publishImport" wire:loading.attr="disabled" class="mt-3 min-h-11 rounded-lg bg-[#1B365D] px-4 font-semibold text-white" wire:target="files,previewImport,publishImport"><span wire:loading.remove wire:target="publishImport">Publish shared package</span><span wire:loading wire:target="publishImport" role="status">Publishing…</span></button>
                </div>
            @endif
        </section>
    @endcan

    @if ($target)
        <section role="region" aria-label="Review package revision" class="my-4 rounded-xl border border-amber-500 p-4">
            <h2 class="font-semibold">Review revision: {{ $target->source_revision }}</h2>
            <ul class="mt-2 space-y-3 text-sm">@forelse ($revisionChanges as $change)<li><p class="font-semibold">{{ $change['id'] }} — {{ $change['status'] }}</p>@foreach ($change['details'] as $detail)<p class="mt-1">{{ $detail }}</p>@endforeach</li>@empty<li>No selected lines to carry forward.</li>@endforelse</ul>
            <div class="mt-3 flex flex-wrap gap-3"><button type="button" wire:click="confirmRevision" class="min-h-11 rounded-lg bg-[#1B365D] px-4 font-semibold text-white" wire:loading.attr="disabled" wire:target="confirmRevision"><span wire:loading.remove wire:target="confirmRevision">Confirm and carry choices forward</span><span wire:loading wire:target="confirmRevision" role="status">Applying…</span></button><button type="button" wire:click="dismissRevision" class="min-h-11 px-3 underline" wire:loading.attr="disabled" wire:target="dismissRevision"><span wire:loading.remove wire:target="dismissRevision">Keep current revision</span><span wire:loading wire:target="dismissRevision" role="status">Keeping revision…</span></button></div>
        </section>
    @elseif ($latest)
        <p class="mt-3 text-sm">A newer package revision is available. <button type="button" wire:click="reviewRevision({{ $latest->id }})" class="min-h-11 px-2 font-semibold underline" wire:loading.attr="disabled" wire:target="reviewRevision"><span wire:loading.remove wire:target="reviewRevision">Review changes</span><span wire:loading wire:target="reviewRevision" role="status">Loading changes…</span></button></p>
    @endif

    @if ($package)
        <div wire:show="showPreferences" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 sm:p-6" x-trap.inert.noscroll="$wire.showPreferences" x-effect="if ($wire.showPreferences) $nextTick(() => $refs.closePreferences.focus({ preventScroll: true }))" @click.self="$wire.showPreferences = false">
            <section id="bid-preferences" role="dialog" aria-modal="true" aria-labelledby="bid-preferences-title" class="flex h-dvh w-full flex-col overflow-hidden border border-slate-200 bg-white text-slate-900 shadow-xl dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 sm:h-auto sm:max-h-[calc(100dvh_-_3rem)] sm:max-w-2xl sm:rounded-xl">
                <header class="flex shrink-0 items-center justify-between gap-3 border-b border-slate-200 px-4 py-3 dark:border-slate-700">
                    <h2 id="bid-preferences-title" class="text-lg font-semibold">Set preferences</h2>
                    <button type="button" x-ref="closePreferences" @click="$wire.showPreferences = false" aria-label="Close preferences" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-full hover:bg-slate-100 focus-visible:ring-2 focus-visible:ring-amber-500 dark:hover:bg-slate-800"><x-heroicon-o-x-mark class="h-5 w-5" aria-hidden="true" /></button>
                </header>
                <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-4">
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Choose goals, then order them. Lower priorities only break ties. Days off and start city exclude nonmatching lines.</p>
                <p x-show="$wire.sort !== 'best'" x-cloak class="mt-2 rounded-lg bg-[#C5A059]/15 px-3 py-2 text-sm text-[#1B365D] dark:text-amber-200">Done switches to Best match to apply your ranking preferences.</p>
                <div x-data="bidGoalPreferences(@js($goalLabels))" @bid-goals-changed.debounce.250ms="$wire.$commit()">
                    <fieldset class="mt-3 flex flex-wrap gap-4" @change="$dispatch('bid-goals-changed')"><legend class="mb-2 text-sm font-semibold">1. Choose goals</legend>@foreach ($goalLabels as $value => $label)<label class="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" wire:model="goals" value="{{ $value }}" class="rounded">{{ $label }}</label>@endforeach</fieldset>
                    <p x-show="$wire.goals.includes('pay')" x-cloak class="mt-2 text-xs text-slate-500 dark:text-slate-400">Maximize pay uses Estimated line value for carry-in lines and Estimated pay for other lines. Complete estimates rank ahead of missing or incomplete estimates when pay is compared.</p>
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
                    <button type="button" wire:click="clearDates" class="min-h-11 text-sm underline" wire:loading.attr="disabled" wire:target="clearDates"><span wire:loading.remove wire:target="clearDates">Clear dates</span><span wire:loading wire:target="clearDates" role="status">Clearing…</span></button>
                </fieldset>
                <details class="mt-2 text-sm"><summary class="cursor-pointer py-2 underline">Select a date range</summary><div class="flex flex-wrap items-end gap-2"><label>From (UTC)<input type="date" min="{{ $packageStartDate }}" max="{{ $packageEndDate }}" wire:model="rangeStart" class="block rounded-lg dark:bg-slate-800"></label><label>Through (UTC)<input type="date" min="{{ $packageStartDate }}" max="{{ $packageEndDate }}" wire:model="rangeEnd" class="block rounded-lg dark:bg-slate-800"></label><button type="button" wire:click="addRange" class="min-h-11 px-3 underline" wire:loading.attr="disabled" wire:target="addRange"><span wire:loading.remove wire:target="addRange">Add range</span><span wire:loading wire:target="addRange" role="status">Adding dates…</span></button></div></details>
                <label class="mt-4 block text-sm font-semibold" for="bid-start-city">3. Required start city</label><select id="bid-start-city" wire:model.change.live="startCity" class="mt-1 rounded-lg text-sm dark:bg-slate-800"><option value="">Any start city</option>@foreach ($cities as $city)<option value="{{ $city }}">{{ $city }}</option>@endforeach</select>
                </div>
                <footer class="flex shrink-0 flex-col gap-3 border-t border-slate-200 p-4 dark:border-slate-700">
                    @if ($errors->any())<div role="alert" class="max-h-24 overflow-y-auto rounded-lg border border-amber-500 p-3 text-sm text-amber-900 dark:text-amber-200">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
                    <p x-ref="preferencesNetworkError" hidden role="alert" class="rounded-lg border border-amber-500 p-3 text-sm">Request failed. Your edits are retained. Check your connection and retry.</p>
                    @if ($dirty)<p class="text-xs text-amber-900 dark:text-amber-200">Use Done to retry saving, or close preferences to reload saved choices.</p>@endif
                    <div class="flex items-center justify-between gap-3">
                    <span class="text-xs text-slate-500 dark:text-slate-400" role="status" aria-live="polite"><span wire:loading>Updating…</span><span wire:loading.remove>{{ $dirty ? 'Unsaved changes' : ($revision > 0 ? 'Saved' : '') }}</span></span>
                    <div x-data="bidAction"><button type="button" @click.debounce.250ms="run(() => $wire.finishSetup())" :disabled="pending" wire:loading.attr="disabled" wire:target="finishSetup" class="min-h-11 rounded-lg bg-[#1B365D] px-4 font-semibold text-white focus-visible:ring-2 focus-visible:ring-amber-500 disabled:opacity-50"><span x-show="! pending">{{ $initialized ? 'Done' : 'Skip setup and browse' }}</span><span x-show="pending" x-cloak role="status">Saving preferences…</span></button></div>
                    </div>
                </footer>
            </section>
        </div>
        @if ($tab === 'trips')
            <section class="mt-4 space-y-3" aria-label="Trips">
                @forelse ($trips as $trip)
                    <details wire:key="trip-{{ $trip['id'] }}" class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"><summary class="cursor-pointer text-sm"><span class="font-mono font-bold">{{ $trip['id'] }} · {{ $trip['start_city'] }}–{{ $trip['end_city'] }}</span> · {{ $trip['start_date'] }}–{{ $trip['end_date'] }} UTC</summary><p class="mt-2 text-sm">@if (filled($trip['report'] ?? null))Report {{ $trip['report'] }} UTC @endif @if (filled($trip['release'] ?? null))Release {{ $trip['release'] }} UTC @endif</p><ul class="mt-2 space-y-1 text-sm">@foreach ($trip['legs'] as $leg)<li class="font-mono">{{ $leg['date'] }} · {{ $leg['origin'] }}–{{ $leg['destination'] }} · {{ $leg['departure'] }}–{{ $leg['arrival'] }} UTC{{ $leg['deadhead'] ? ' · Deadhead' : '' }}</li>@endforeach</ul></details>
                @empty<p class="rounded-xl border border-slate-200 p-5 dark:border-slate-700">Trips file not imported or no supported trip details were found. @can('manage-crew-bids')Use Manage package to import Trips.@else An admin can update this package.@endcan</p>@endforelse
            </section>
        @else
            <div id="bid-filters" x-show="filtersOpen" x-cloak class="mb-3 flex flex-col gap-3" role="region" aria-label="Line filters">
                <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 text-xs">
                    <p class="min-w-0 text-slate-600 dark:text-slate-300" role="status">{{ $activeFilterSummary }}</p>
                    <button type="button" wire:click="resetFilters" class="min-h-11 shrink-0 rounded-lg px-2 font-semibold underline focus-visible:ring-2 focus-visible:ring-amber-500" wire:loading.attr="disabled" wire:target="resetFilters"><span wire:loading.remove wire:target="resetFilters">Reset filters</span><span wire:loading wire:target="resetFilters" role="status">Resetting…</span></button>
                </div>
                <div class="grid gap-3 lg:grid-cols-3">
                    <x-crew-bids.filter-group label="Credit & crew" :summary="$filterSummaries['credit']" clear-action="clearCreditFilters" clear-label="Clear credit" wire:key="bid-credit-filters">
                        <label for="bid-minimum-credit" class="font-semibold">Min credit (h)</label>
                        <div class="flex flex-wrap gap-2" role="group" aria-label="Quick minimum credit">
                            @foreach ([64, 70, 80] as $hours)
                                <button type="button" @click="$wire.$set('minimumCredit', {{ $hours }})" :aria-pressed="$wire.minimumCredit === {{ $hours }}" :class="$wire.minimumCredit === {{ $hours }} ? 'border-[#C5A059] bg-[#C5A059]/15 text-[#1B365D] dark:text-amber-200' : 'border-slate-300 text-slate-600 dark:border-slate-600 dark:text-slate-300'" aria-label="Set minimum credit to {{ $hours }} hours" wire:loading.attr="disabled" wire:target="minimumCredit" class="min-h-11 rounded-full border px-3 font-mono font-semibold focus-visible:ring-2 focus-visible:ring-amber-500 disabled:opacity-50">{{ $hours }}h</button>
                            @endforeach
                        </div>
                        <input id="bid-minimum-credit" type="number" min="0" max="300" wire:model.live.debounce.400ms="minimumCredit" aria-describedby="bid-minimum-credit-error" aria-invalid="{{ $errors->has('minimumCredit') ? 'true' : 'false' }}" class="min-h-11 w-full rounded-lg text-sm focus-visible:ring-2 focus-visible:ring-amber-500 dark:bg-slate-900">
                        <p id="bid-minimum-credit-error" class="text-amber-800 empty:hidden dark:text-amber-300">@error('minimumCredit'){{ $message }}@enderror</p>
                    </x-crew-bids.filter-group>
                    <x-crew-bids.filter-group label="Work pattern" :summary="$filterSummaries['work_pattern']" clear-action="clearWorkPatternFilters" clear-label="Clear work pattern" wire:key="bid-work-pattern-filters">
                        <label for="bid-minimum-days-off" class="font-semibold">Min days off</label>
                        <div class="flex flex-wrap gap-2" role="group" aria-label="Quick minimum days off">
                            @foreach ([14, 15, 16] as $days)
                                <button type="button" @click="$wire.$set('minimumDaysOff', {{ $days }})" :aria-pressed="$wire.minimumDaysOff === {{ $days }}" :class="$wire.minimumDaysOff === {{ $days }} ? 'border-[#C5A059] bg-[#C5A059]/15 text-[#1B365D] dark:text-amber-200' : 'border-slate-300 text-slate-600 dark:border-slate-600 dark:text-slate-300'" aria-label="Set minimum days off to {{ $days }} days" wire:loading.attr="disabled" wire:target="minimumDaysOff" class="min-h-11 rounded-full border px-3 font-mono font-semibold focus-visible:ring-2 focus-visible:ring-amber-500 disabled:opacity-50">{{ $days }} days</button>
                            @endforeach
                        </div>
                        <input id="bid-minimum-days-off" type="number" min="0" max="31" wire:model.live.debounce.400ms="minimumDaysOff" aria-describedby="bid-minimum-days-off-error" aria-invalid="{{ $errors->has('minimumDaysOff') ? 'true' : 'false' }}" class="min-h-11 w-full rounded-lg text-sm focus-visible:ring-2 focus-visible:ring-amber-500 dark:bg-slate-900">
                        <p id="bid-minimum-days-off-error" class="text-amber-800 empty:hidden dark:text-amber-300">@error('minimumDaysOff'){{ $message }}@enderror</p>
                    </x-crew-bids.filter-group>
                    <x-crew-bids.filter-group label="Airports" :summary="$filterSummaries['airports']" clear-action="clearLayoverFilters" clear-label="Clear airports" wire:key="bid-airport-filters">
                        <label for="bid-layover-city" class="font-semibold">Layover airport</label>
                        <select id="bid-layover-city" wire:model.change.live="layoverCity" aria-describedby="bid-layover-city-error" aria-invalid="{{ $errors->has('layoverCity') ? 'true' : 'false' }}" class="min-h-11 w-full rounded-lg font-mono text-sm focus-visible:ring-2 focus-visible:ring-amber-500 dark:bg-slate-900"><option value="">Any layover airport</option>@foreach ($layoverCities as $city)<option value="{{ $city }}">{{ $city }}</option>@endforeach</select>
                        <p id="bid-layover-city-error" class="text-amber-800 empty:hidden dark:text-amber-300">@error('layoverCity'){{ $message }}@enderror</p>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" @click="$wire.$toggle('extendedLayoversOnly')" :aria-pressed="$wire.extendedLayoversOnly" :class="$wire.extendedLayoversOnly ? 'border-[#C5A059] bg-[#C5A059]/15 text-[#1B365D] dark:text-amber-200' : 'border-slate-300 text-slate-600 dark:border-slate-600 dark:text-slate-300'" class="min-h-11 rounded-full border px-3 font-semibold focus-visible:ring-2 focus-visible:ring-amber-500">24h+ layover</button>
                            <button type="button" @click="$wire.$toggle('avoidShortLayovers')" :aria-pressed="$wire.avoidShortLayovers" :class="$wire.avoidShortLayovers ? 'border-[#C5A059] bg-[#C5A059]/15 text-[#1B365D] dark:text-amber-200' : 'border-slate-300 text-slate-600 dark:border-slate-600 dark:text-slate-300'" class="min-h-11 rounded-full border px-3 font-semibold focus-visible:ring-2 focus-visible:ring-amber-500">Avoid under 10h</button>
                        </div>
                        @if ($filterSummaries['airports'] !== [])
                            <p class="text-slate-500 dark:text-slate-400">Matching layovers are outlined in gold in the trip timeline. Avoid under 10h applies to every airport and requires complete connection data. Your bid list keeps its order.</p>
                        @elseif ($layoverCities === [])
                            <p class="text-slate-500 dark:text-slate-400">No connected layover data is available for these lines.</p>
                        @endif
                    </x-crew-bids.filter-group>
                </div>
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <label class="flex min-h-11 items-center gap-2"><input type="checkbox" wire:model.change.live="selectedOnly" class="rounded focus-visible:ring-2 focus-visible:ring-amber-500">Selected only</label>
                </div>
            </div>
            <p wire:loading.delay wire:target="tab,sort,daysOff,goals,startCity,minimumCredit,minimumDaysOff,layoverCity,extendedLayoversOnly,avoidShortLayovers,selectedOnly,changeContext,resetFilters,clearCreditFilters,clearWorkPatternFilters,clearLayoverFilters,clearDates,addRange" role="status" class="mb-2 text-xs text-slate-500 dark:text-slate-400">Updating matching lines…</p>
            <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">Credit h:mm[:ss] · Block h:mm · UTC · Blue: flying trip (includes layover/rest) · Orange: reserve · Light: off · ?: unknown</p>
            <section class="space-y-3" aria-label="Available lines" wire:loading.class="opacity-60" wire:target="tab,sort,daysOff,goals,startCity,minimumCredit,minimumDaysOff,layoverCity,extendedLayoversOnly,avoidShortLayovers,selectedOnly,changeContext,resetFilters,clearCreditFilters,clearWorkPatternFilters,clearLayoverFilters,clearDates,addRange">
                @forelse ($lineCards as $line)<x-crew-bids.line :line="$line" :prediction="$prediction['lines'][$line->id] ?? null" wire:key="bid-line-{{ $packageId }}-{{ $line->id }}" />
                @empty<div class="rounded-xl border border-slate-200 p-5 dark:border-slate-700"><p>No lines match these filters.</p><button type="button" wire:click="resetFilters" class="min-h-11 font-semibold underline" wire:loading.attr="disabled" wire:target="resetFilters"><span wire:loading.remove wire:target="resetFilters">Reset filters</span><span wire:loading wire:target="resetFilters" role="status">Resetting…</span></button></div>@endforelse
            </section>
        @endif
    @else
        <section class="my-8 rounded-xl border border-slate-200 bg-white p-8 text-center dark:border-slate-700 dark:bg-slate-900"><h2 class="text-lg font-semibold">No bid package available yet</h2><p class="mt-2 text-sm text-slate-500 dark:text-slate-400">@can('manage-crew-bids')Open Manage package to import and publish a bid package.@else An admin will publish packages here when available.@endcan</p></section>
    @endif

        <div id="bid-list-drawer" wire:show="showList" x-cloak class="fixed inset-0 z-50 bg-black/40" x-trap.inert.noscroll="$wire.showList" x-effect="if ($wire.showList) $nextTick(() => $refs.closeList.focus({ preventScroll: true }))" @click.self="closeList()">
            <section role="dialog" aria-modal="true" aria-labelledby="bid-list-title" class="absolute inset-0 flex flex-col bg-white dark:bg-slate-950 sm:start-auto sm:w-full sm:max-w-xl">
                <header class="flex items-center justify-between border-b border-slate-200 p-4 dark:border-slate-700"><h2 id="bid-list-title" class="text-lg font-semibold">{{ $showReview ? 'Review bid list' : 'My bid list' }} ({{ count($selections) }})</h2><button x-ref="closeList" type="button" @click="closeList()" aria-label="Close bid list" class="inline-flex min-h-11 items-center gap-1.5 rounded-lg px-3 text-sm font-semibold hover:bg-slate-100 focus-visible:ring-2 focus-visible:ring-amber-500 dark:hover:bg-slate-800"><span>Close</span><x-heroicon-o-x-mark class="h-5 w-5 shrink-0" aria-hidden="true" /></button></header>
                <div class="flex-1 space-y-3 overflow-y-auto p-4">
                    @if ($package)<p class="text-sm">{{ $monthLabel }} · B{{ $package->fleet }} · {{ $seat }}</p>@endif
                    <section aria-label="Local save and Kalitta submission status" class="space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm dark:border-slate-700 dark:bg-slate-900">
                        <div class="flex items-start gap-2">
                            <span class="mt-0.5 inline-flex h-5 w-5 shrink-0" aria-hidden="true">
                                <x-heroicon-o-document-check x-cloak x-show="saveState() === 'saved'" class="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                                <x-heroicon-o-document-text x-cloak x-show="saveState() === 'empty'" class="h-5 w-5 text-slate-500 dark:text-slate-400" />
                                <x-heroicon-o-arrow-path x-cloak x-show="saveState() === 'saving'" class="h-5 w-5 animate-spin text-amber-600 motion-reduce:animate-none dark:text-amber-300" />
                                <x-heroicon-o-exclamation-triangle x-cloak x-show="saveState() === 'unsaved'" class="h-5 w-5 text-amber-600 dark:text-amber-300" />
                            </span>
                            <div class="min-w-0"><p class="font-semibold" x-text="saveLabel()">Local save</p><p class="text-xs text-slate-600 dark:text-slate-300" x-text="saveDescription()"></p><p class="mt-1 text-xs text-slate-600 dark:text-slate-300" x-text="saveHelp()"></p>
                                <button type="button" x-cloak x-show="saveState() === 'unsaved' && ! $wire.$get('dirty')" wire:click="retrySave" wire:loading.attr="disabled" wire:target="retrySave" class="mt-1 min-h-11 font-semibold underline focus-visible:ring-2 focus-visible:ring-amber-500">Retry local save</button>
                            </div>
                        </div>
                        <div class="flex items-start gap-2 border-t border-slate-200 pt-3 dark:border-slate-700">
                            <x-heroicon-o-paper-airplane @class(['mt-0.5 h-5 w-5 shrink-0', 'text-emerald-600 dark:text-emerald-400' => $submissionStatus->state === 'matched', 'text-amber-600 dark:text-amber-300' => $submissionStatus->state === 'changed', 'text-slate-500 dark:text-slate-400' => $submissionStatus->state === 'unconfirmed']) aria-hidden="true" />
                            <div><p class="font-semibold">{{ $submissionStatus->label }}</p><p class="text-xs text-slate-600 dark:text-slate-300">{{ $submissionStatus->description }}</p>
                                @if ($package && config('crew_bids.submission.enabled'))<p class="mt-1 text-xs text-slate-600 dark:text-slate-300">Open Review / Copy, then Submit to Kalitta to review and confirm your bid.</p>@endif
                            </div>
                        </div>
                    </section>
                    @if ($showReview)
                        <p class="text-sm text-slate-500 dark:text-slate-400">Review your order and warnings. Copying does not submit your bid.</p>
                        <textarea id="bid-copy-text" readonly rows="8" aria-label="Numbered bid list for copying" class="w-full rounded-lg font-mono text-sm dark:bg-slate-900">{{ $copyText }}</textarea>
                        @if ($package && config('crew_bids.submission.enabled'))
                            <form method="GET" action="{{ route('crew-bids.submissions.create', ['package' => $package, 'seat' => $seat]) }}">
                                <button type="submit" wire:loading.attr="disabled" wire:target="move,remove,clearList,addMatching,undoAdd,retrySave" :disabled="hasUnsavedChanges() || $wire.selections.length === 0" @disabled($dirty || $selections === []) class="min-h-11 rounded-lg bg-[#C5A059] px-4 font-semibold text-[#0B0E14] focus-visible:ring-2 focus-visible:ring-amber-500 disabled:opacity-50">Submit to Kalitta</button>
                            </form>
                        @endif
                        @if ($aiPrompt !== null)
                            <button x-ref="aiPromptTrigger" type="button" @click="$refs.aiPromptDialog.showModal()" aria-haspopup="dialog" aria-controls="bid-ai-prompt" wire:loading.attr="disabled" wire:target="move,remove,clearList,addMatching,undoAdd,retrySave" :disabled="hasUnsavedChanges()" class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-[#C5A059] px-3 text-sm font-semibold text-[#1B365D] focus-visible:ring-2 focus-visible:ring-amber-500 disabled:opacity-50 dark:text-amber-200">AI form prompt</button>
                            <dialog id="bid-ai-prompt" x-ref="aiPromptDialog" wire:ignore.self aria-labelledby="bid-ai-prompt-title" @keydown.escape.stop @click="if ($event.target === $el) $el.close()" @close="$refs.aiPromptTrigger.focus()" class="fixed inset-0 m-auto max-h-[80dvh] w-[calc(100%_-_2rem)] max-w-2xl overflow-y-auto rounded-xl border border-slate-200 bg-white p-0 text-slate-900 shadow-xl backdrop:bg-slate-950/50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
                                <section class="space-y-4 p-5">
                                    <div class="flex items-center justify-between gap-3"><h3 id="bid-ai-prompt-title" class="text-lg font-semibold">AI form prompt</h3><button type="button" @click="$refs.aiPromptDialog.close()" aria-label="Close AI form prompt" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-full hover:bg-slate-100 focus-visible:ring-2 focus-visible:ring-amber-500 dark:hover:bg-slate-800"><x-heroicon-o-x-mark class="h-5 w-5" aria-hidden="true" /></button></div>
                                    <p class="text-sm text-slate-600 dark:text-slate-300">Copy this into your browser agent while on the bid form. Check the filled values before submitting.</p>
                                    <textarea id="bid-ai-prompt-text" readonly rows="10" aria-label="AI prompt for filling the bid form" class="w-full rounded-lg border border-slate-300 bg-slate-50 font-mono text-sm focus-visible:ring-2 focus-visible:ring-amber-500 dark:border-slate-700 dark:bg-slate-950">{{ $aiPrompt }}</textarea>
                                    <div><button type="button" data-bid-copy data-bid-copy-target="bid-ai-prompt-text" wire:loading.attr="disabled" wire:target="move,remove,clearList,addMatching,undoAdd,retrySave" :disabled="hasUnsavedChanges()" class="min-h-11 rounded-lg bg-[#1B365D] px-4 font-semibold text-white disabled:opacity-50">Copy AI prompt</button><span data-bid-copy-status role="status" class="ms-2 text-sm"></span></div>
                                </section>
                            </dialog>
                        @elseif ($selections !== [])
                            <p class="text-sm text-amber-800 dark:text-amber-300">An AI form prompt is unavailable because this list has an unavailable, invalid, or repeated portal line number.</p>
                        @endif
                    @endif
                    <p wire:loading wire:target="move,remove,clearList,addMatching,undoAdd" role="status" class="mb-2 text-xs text-slate-500 dark:text-slate-400">Saving bid list…</p>
                    <ol wire:sort="move" class="space-y-2" wire:loading.class="opacity-60" wire:target="move,remove,clearList,addMatching,undoAdd">@forelse ($selected as $index => $line)<li wire:key="rank-{{ $packageId }}-{{ $line['id'] }}" wire:sort:item="{{ $line['id'] }}" class="rounded-lg border border-slate-200 p-3 dark:border-slate-700"><div class="flex flex-wrap items-center gap-2 text-sm"><span wire:sort:handle aria-hidden="true">⠿</span><span class="font-mono font-semibold">{{ $index + 1 }}. {{ $line['id'] }}</span>@if ($line['credit_label'] !== 'Not available')<span>Credit {{ $line['credit_label'] }}</span>@endif @if ($line['block_label'] !== 'Not available')<span>Block {{ $line['block_label'] }}</span>@endif @if (isset($line['days_off']))<span>Days off {{ $line['days_off'] }}</span>@endif<details class="ms-auto"><summary class="cursor-pointer py-2 underline">Move</summary><div class="flex flex-wrap gap-2">@if ($index > 0)<button type="button" wire:click="move('{{ $line['id'] }}', {{ $index - 1 }})" class="min-h-11 underline" aria-label="Move {{ $line['id'] }} up" wire:loading.attr="disabled" wire:target="move"><span wire:loading.remove wire:target="move">Up</span><span wire:loading wire:target="move" role="status">Saving order…</span></button>@endif @if ($index < count($selected) - 1)<button type="button" wire:click="move('{{ $line['id'] }}', {{ $index + 1 }})" class="min-h-11 underline" aria-label="Move {{ $line['id'] }} down" wire:loading.attr="disabled" wire:target="move"><span wire:loading.remove wire:target="move">Down</span><span wire:loading wire:target="move" role="status">Saving order…</span></button>@endif<label class="text-xs">Position<select wire:change="move('{{ $line['id'] }}', $event.target.value)" wire:loading.attr="disabled" wire:target="move" class="rounded-lg text-sm dark:bg-slate-800">@foreach ($selected as $position => $choice)<option value="{{ $position }}" @selected($position === $index)>{{ $position + 1 }}</option>@endforeach</select></label></div></details><button type="button" wire:click="remove('{{ $line['id'] }}')" aria-label="Remove {{ $line['id'] }}" class="min-h-11 px-2 underline" wire:loading.attr="disabled" wire:target="remove"><span wire:loading.remove wire:target="remove">Remove</span><span wire:loading wire:target="remove" role="status">Removing…</span></button></div>@foreach ($line['warnings'] as $warning)<p class="mt-2 text-sm text-amber-800 dark:text-amber-300">{{ $warning }}</p>@endforeach</li>@empty<li class="py-8 text-sm">Your bid list is empty. Return to lines and select Add to start.</li>@endforelse</ol>
                    <details class="text-sm"><summary class="cursor-pointer py-3 underline">More actions</summary><div class="flex flex-wrap gap-3"><button type="button" wire:click="clearList" @disabled($selections === []) wire:confirm="Clear all choices from this bid list?" class="min-h-11 underline" wire:loading.attr="disabled" wire:target="clearList"><span wire:loading.remove wire:target="clearList">Clear bid list</span><span wire:loading wire:target="clearList" role="status">Clearing…</span></button><button type="button" wire:click="addMatching" @disabled($lines === []) class="min-h-11 underline" wire:loading.attr="disabled" wire:target="addMatching"><span wire:loading.remove wire:target="addMatching">Add all {{ count($lines) }} matching {{ $tab === 'reserve' ? 'reserve' : 'flying' }} lines</span><span wire:loading wire:target="addMatching" role="status">Adding lines…</span></button></div></details>
                </div>
                <footer class="border-t border-slate-200 p-4 dark:border-slate-700"><p role="status" class="mb-2 text-sm">{{ $dirty ? 'Unsaved changes — Retry save before copying' : ($revision > 0 ? 'Saved' : '') }}</p>@if ($dirty)<button type="button" wire:click="retrySave" class="min-h-11 px-3 underline" wire:loading.attr="disabled" wire:target="retrySave"><span wire:loading.remove wire:target="retrySave">Retry save</span><span wire:loading wire:target="retrySave" role="status">Saving…</span></button>@elseif ($showReview)<button type="button" data-bid-copy wire:loading.attr="disabled" :disabled="hasUnsavedChanges() || $wire.selections.length === 0" class="min-h-11 rounded-lg bg-[#1B365D] px-4 font-semibold text-white" @disabled($selections === [])>Copy numbered list</button><span data-bid-copy-status role="status" class="ms-2 text-sm"></span>@else<button type="button" wire:click="$set('showReview', true)" class="min-h-11 rounded-lg bg-[#1B365D] px-4 font-semibold text-white" @disabled($selections === []) wire:loading.attr="disabled" wire:target="showReview"><span wire:loading.remove wire:target="showReview">Review / Copy</span><span wire:loading wire:target="showReview" role="status">Preparing review…</span></button>@endif</footer>
            </section>
        </div>
</div>
