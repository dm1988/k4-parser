@props(['viewing' => false, 'syncing' => false, 'idPrefix' => 'portal', 'allowSaved' => true])
<div x-data="{ saved: @js($allowSaved && $connection['unlocked']) }" class="space-y-4">
@if ($allowSaved && $connection['connected'])
    <div class="space-y-2 text-sm">
        @if ($connection['unlocked'])
            <label class="flex min-h-11 items-center gap-3"><input type="checkbox" name="use_saved_credentials" value="1" x-model="saved" class="rounded border-slate-300 focus:ring-[#1B365D]" checked>Use saved K4 login ({{ $connection['hint'] }})</label>
        @else
            <p>Your saved K4 connection is locked. <a href="{{ route('crew-bids.connection.index') }}" class="font-semibold underline">Unlock it with your app password</a>, or enter a one-off login below.</p>
        @endif
    </div>
@elseif ($allowSaved && config('crew_bids.vault.enabled'))
    <p class="text-sm"><a href="{{ route('crew-bids.connection.index') }}" class="font-semibold underline">Save a K4 connection</a> to reuse your login after a session unlock.</p>
@endif
<fieldset x-show="!saved" :disabled="saved" @disabled($allowSaved && $connection['unlocked']) class="grid gap-4 sm:grid-cols-2">
    <label for="{{ $idPrefix }}-employee" class="block text-sm font-medium">Employee number
        <input id="{{ $idPrefix }}-employee" name="employee" type="text" inputmode="numeric" pattern="[0-9]{1,20}" maxlength="20" required autocomplete="off" autocapitalize="none" spellcheck="false" data-lpignore="true" data-1p-ignore data-bwignore readonly x-init="$el.value = ''" @focus="$el.removeAttribute('readonly')" @pageshow.window="$el.value = ''; $el.setAttribute('readonly', 'readonly')" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#1B365D] focus:ring-[#1B365D] dark:border-slate-700 dark:bg-slate-950">
    </label>
    <label for="{{ $idPrefix }}-password" class="block text-sm font-medium">Kalitta portal password
        <input id="{{ $idPrefix }}-password" name="portal_password" type="password" maxlength="256" required autocomplete="new-password" data-lpignore="true" data-1p-ignore data-bwignore readonly x-init="$el.value = ''" @focus="$el.removeAttribute('readonly')" @pageshow.window="$el.value = ''; $el.setAttribute('readonly', 'readonly')" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#1B365D] focus:ring-[#1B365D] dark:border-slate-700 dark:bg-slate-950">
    </label>
</fieldset>
<p class="text-sm text-slate-600 dark:text-slate-300">One-off logins are held temporarily in memory for this operation. Saved connections retain password-encrypted ciphertext; usable keys expire or are revoked when you lock or log out. {{ $viewing ? 'Reading stored shared bids does not require a login.' : 'Using a saved login does not replace review or final submission confirmation.' }}</p>
</div>
