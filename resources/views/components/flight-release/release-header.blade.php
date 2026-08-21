@props(['model'])

<section
    {{ $attributes->merge(['class' => 'overflow-hidden rounded-xl border border-[#1B365D]/10 bg-[#F8F9FA] p-3 shadow-sm dark:border-slate-700 dark:bg-slate-800/70']) }}
    aria-label="Release summary"
>
    <dl class="grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-6">
        <x-flight-release.metric
            label="Flight"
            :value="$model->flightNumber()"
            :meta="$model->flightDate()"
        />
        <x-flight-release.metric
            label="Aircraft"
            :value="$model->aircraftType()"
            :meta="$model->tailNumber() ? 'Tail '.$model->tailNumber() : null"
        />
        <x-flight-release.metric
            label="Route"
            :value="$model->departure().' → '.$model->destination()"
            class="col-span-2 sm:col-span-1"
        />
        <x-flight-release.metric label="ETD (UTC)" :value="$model->etdUtc()" />
        <x-flight-release.metric label="ETA (UTC)" :value="$model->etaUtc()" />
        @if ($model->releaseRevision())
            <x-flight-release.metric label="Release revision" :value="$model->releaseRevision()" />
        @endif
    </dl>
</section>
