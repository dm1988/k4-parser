<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div>
        <div class="flex flex-col gap-1.5">
            @if ($model->aircraft())
                <p class="text-sm font-semibold text-[#111827] dark:text-slate-100">
                    {{ $model->aircraft() }}
                </p>
            @endif
            @if ($model->tailNumber())
                <p class="text-sm font-semibold text-[#111827] dark:text-slate-100">
                    {{ $model->tailNumber() }}
                </p>
            @endif
        </div>
    </div>
</div>
