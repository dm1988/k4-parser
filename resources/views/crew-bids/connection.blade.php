<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold">K4 connection</h1></x-slot>
    <section class="mx-auto my-6 max-w-2xl space-y-5 rounded-xl bg-white p-5 text-slate-900 shadow dark:bg-slate-900 dark:text-slate-100">
        <a href="{{ route('crew-bids.index') }}" class="inline-flex min-h-11 items-center underline focus-visible:ring-2 focus-visible:ring-amber-500">Back to K4 Bids</a>
        <p class="text-sm">Save your K4 login encrypted with your app password. Unlock it each session to reuse it. It locks after 15 minutes without a K4 operation, after two hours, or when your app session ends. Saving or unlocking never submits bids.</p>
        <p role="status" class="font-semibold">{{ $connection['connected'] ? 'Connected '.$connection['hint'].' · '.($connection['unlocked'] ? 'Unlocked for this session' : 'Locked') : 'No saved K4 connection' }}</p>
        @if (! config('crew_bids.vault.enabled'))<p role="status" class="text-sm">Saved K4 login reuse is disabled. You can still forget your saved connection.</p>@endif
        @if (session('crew_bid_sync_notice'))<p role="status" class="rounded-lg border border-[#C5A059] p-3 text-sm">{{ session('crew_bid_sync_notice') }}</p>@endif
        @if (session('crew_bid_sync_error'))<p role="alert" class="rounded-lg border border-amber-500 p-3 text-sm">{{ session('crew_bid_sync_error') }}</p>@endif
        @if ($errors->any())<p role="alert" class="rounded-lg border border-amber-500 p-3 text-sm">{{ $errors->first() }}</p>@endif
        @if ($connection['connected'])
            @if ($connection['unlocked'])
                <form method="POST" action="{{ route('crew-bids.connection.lock') }}">
                    @csrf @method('DELETE')
                    <button class="min-h-11 rounded-lg border border-slate-300 px-4 text-sm font-semibold dark:border-slate-700">Lock this session</button>
                </form>
            @elseif (config('crew_bids.vault.enabled'))
                <form method="POST" action="{{ route('crew-bids.connection.unlock') }}" autocomplete="off" class="space-y-3">
                    @csrf
                    <label class="block text-sm font-medium">App password<input name="app_password" type="password" required maxlength="1024" autocomplete="current-password" class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950"></label>
                    <button class="min-h-11 rounded-lg bg-[#1B365D] px-4 text-sm font-semibold text-white focus-visible:ring-2 focus-visible:ring-amber-500">Unlock K4 connection</button>
                </form>
            @endif
        @endif
        @if (config('crew_bids.vault.enabled'))
        <details @if (! $connection['connected']) open @endif class="space-y-4 rounded-lg border border-slate-300 p-4 dark:border-slate-700">
            <summary class="cursor-pointer font-semibold">{{ $connection['connected'] ? 'Update K4 credentials' : 'Connect K4 account' }}</summary>
            <form method="POST" action="{{ route('crew-bids.connection.store') }}" autocomplete="off" class="space-y-4" x-data="{ busy: false }" @submit="busy = true">
                @csrf
                <label class="block text-sm font-medium">App password<input name="app_password" type="password" required maxlength="1024" autocomplete="current-password" class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950"></label>
                <x-crew-bids.portal-credentials :allow-saved="false" id-prefix="connection" />
                <label class="flex min-h-11 items-start gap-3 text-sm"><input type="checkbox" name="confirmed" value="1" required class="mt-1 rounded border-slate-300">Save this K4 login as password-encrypted data on the server. Updating replaces the saved connection and revokes its other unlocked sessions.</label>
                <button :disabled="busy" class="min-h-11 rounded-lg bg-[#1B365D] px-4 text-sm font-semibold text-white focus-visible:ring-2 focus-visible:ring-amber-500 disabled:opacity-50"><span x-show="!busy">Verify and save connection</span><span x-show="busy" x-cloak>Verifying with K4…</span></button>
            </form>
        </details>
        @endif
        @if ($connection['connected'])
            <details class="space-y-4 rounded-lg border border-slate-300 p-4 dark:border-slate-700">
                <summary class="cursor-pointer font-semibold">Forget K4 connection</summary>
                <p class="text-sm">Removes the saved connection and revokes its unlocked sessions and queued logins. Your bid lists and existing reports are kept.</p>
                <form method="POST" action="{{ route('crew-bids.connection.destroy') }}" autocomplete="off" class="space-y-3">
                    @csrf @method('DELETE')
                    <label class="block text-sm font-medium">App password<input name="app_password" type="password" required maxlength="1024" autocomplete="current-password" class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950"></label>
                    <button class="min-h-11 rounded-lg bg-red-700 px-4 text-sm font-semibold text-white focus-visible:ring-2 focus-visible:ring-amber-500">Forget connection</button>
                </form>
            </details>
        @endif
        <p class="text-sm text-slate-600 dark:text-slate-300">A forgotten app password cannot recover this connection. After an account password reset, reconnect with your K4 login. The server uses plaintext in memory while contacting K4; this protects stored credentials rather than a compromised running server.</p>
    </section>
</x-app-layout>
