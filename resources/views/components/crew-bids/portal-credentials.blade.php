<div class="grid gap-4 sm:grid-cols-2">
    <label for="portal-employee" class="block text-sm font-medium">Employee number
        <input id="portal-employee" name="employee" type="text" inputmode="numeric" pattern="[0-9]{1,20}" maxlength="20" required autocomplete="off" autocapitalize="none" spellcheck="false" data-lpignore="true" data-1p-ignore data-bwignore readonly x-init="$el.value = ''" @focus="$el.removeAttribute('readonly')" @pageshow.window="$el.value = ''; $el.setAttribute('readonly', 'readonly')" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#1B365D] focus:ring-[#1B365D] dark:border-slate-700 dark:bg-slate-950">
    </label>
    <label for="portal-password" class="block text-sm font-medium">Kalitta portal password
        <input id="portal-password" name="portal_password" type="password" maxlength="256" required autocomplete="new-password" data-lpignore="true" data-1p-ignore data-bwignore readonly x-init="$el.value = ''" @focus="$el.removeAttribute('readonly')" @pageshow.window="$el.value = ''; $el.setAttribute('readonly', 'readonly')" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#1B365D] focus:ring-[#1B365D] dark:border-slate-700 dark:bg-slate-950">
    </label>
</div>
<p class="text-sm text-slate-600 dark:text-slate-300">Credentials are used for this operation and discarded. Enter them again for final submission.</p>
