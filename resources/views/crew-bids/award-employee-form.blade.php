<form wire:submit="identifyAwardEmployee" class="mt-4 flex flex-wrap items-end gap-3">
    <label class="text-sm font-semibold">Employee number (Crew ID)<input type="text" inputmode="numeric" pattern="[0-9]{1,20}" maxlength="20" wire:model="awardForm.employeeNumber" required class="mt-1 block w-48 rounded-lg text-sm dark:border-slate-600 dark:bg-slate-950"></label>
    <button type="submit" wire:loading.attr="disabled" wire:target="identifyAwardEmployee" class="min-h-11 rounded-lg bg-[#1B365D] px-4 text-sm font-semibold text-white focus-visible:ring-2 focus-visible:ring-amber-500 disabled:opacity-50">Find my award</button>
    @error('awardForm.employeeNumber')<p role="alert" class="w-full text-sm text-amber-800 dark:text-amber-200">{{ $message }}</p>@enderror
    <p class="w-full text-xs text-slate-500 dark:text-slate-400">Used for uploaded awards in this app session. Your verified K4 account link is unchanged.</p>
</form>
