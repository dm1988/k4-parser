@props(['viewing' => false])

<details data-block-bid-refresh x-data class="relative shrink-0 text-left open:z-50"
    @click.outside="$el.open = false"
    @keydown.escape.window="if ($el.open) { $event.preventDefault(); $el.open = false; $refs.trigger.focus(); }">
    <summary x-ref="trigger" aria-label="How K4 login security works" title="K4 login security"
        class="flex h-11 w-11 cursor-pointer list-none items-center justify-center rounded-lg text-slate-500 transition-colors hover:bg-slate-100 hover:text-[#1B365D] focus-visible:ring-2 focus-visible:ring-amber-500 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-amber-200 [&::-webkit-details-marker]:hidden">
        <x-heroicon-o-question-mark-circle class="h-5 w-5" aria-hidden="true" />
        <span class="sr-only">How K4 login security works</span>
    </summary>
    <div role="note" tabindex="0" aria-label="K4 login security details" class="absolute right-0 top-full z-50 mt-2 max-h-[min(32rem,65dvh)] w-96 max-w-[calc(100vw-3rem)] space-y-4 overflow-y-auto overscroll-contain rounded-xl border border-slate-200 bg-white p-4 text-sm leading-relaxed text-slate-700 shadow-xl focus-visible:ring-2 focus-visible:ring-amber-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
        <p class="font-semibold text-[#1B365D] dark:text-amber-200">How your K4 login is protected</p>
        <p>Save your K4 login encrypted with your app password. Unlock it each session to reuse it. It locks after 15 minutes without a K4 operation, after two hours, or when your app session ends. Saving or unlocking never submits bids.</p>
        <p>One-off logins are held temporarily in memory for this operation. Saved connections retain password-encrypted ciphertext; usable keys expire or are revoked when you lock or log out. {{ $viewing ? 'Reading stored shared bids does not require a login.' : 'Using a saved login does not replace review or final submission confirmation.' }}</p>
        <dl class="space-y-3">
            <div>
                <dt class="font-semibold">Encryption and password protection</dt>
                <dd>PHP Sodium / libsodium uses XChaCha20-Poly1305 authenticated encryption with a random 256-bit data key and fresh nonces. Argon2id derives the wrapping key from your app password with a random salt, 64 MiB of memory and two passes. The server's app key alone cannot unlock this vault.</dd>
            </div>
            <div>
                <dt class="font-semibold">Temporary storage</dt>
                <dd>Unlocked keys and queued logins use a separate memory-only service with persistence disabled. Queued logins expire within ten minutes and can be claimed only once. There is no fallback to database credential caching.</dd>
            </div>
            <div>
                <dt class="font-semibold">Connection protocols</dt>
                <dd>K4 requests use HTTPS / TLS with certificate verification. Production connections to the memory service also require TLS and authentication.</dd>
            </div>
            <div>
                <dt class="font-semibold">Recovery and limits</dt>
                <dd>A forgotten app password cannot recover this connection. After an account password reset, reconnect with your K4 login. The server uses plaintext in memory while contacting K4; this protects stored credentials rather than a compromised running server.</dd>
            </div>
        </dl>
    </div>
</details>
