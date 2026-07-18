<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        {{ __('Waiting for verification... Use the link in the email we sent you, or enter the 6-digit code below.') }}
    </div>

    <form method="POST" action="{{ route('verification.verify-otp') }}">
        @csrf

        <div>
            <x-input-label for="otp" :value="__('Enter 6-digit code')" />
            <x-text-input
                id="otp"
                class="mt-1 block w-full text-center text-xl tracking-[0.5em]"
                type="text"
                name="otp"
                :value="old('otp')"
                required
                autofocus
                inputmode="numeric"
                autocomplete="one-time-code"
                maxlength="6"
                pattern="[0-9]{6}"
            />
            <x-input-error :messages="$errors->get('otp')" class="mt-2" />
        </div>

        <x-primary-button class="mt-4">
            {{ __('Verify Email') }}
        </x-primary-button>
    </form>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 font-medium text-sm text-green-600">
            {{ __('A new verification link has been sent to the email address you provided during registration.') }}
        </div>
    @endif

    <div class="mt-6 flex items-center justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <div>
                <x-primary-button>
                    {{ __('Resend Verification Email') }}
                </x-primary-button>
            </div>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                {{ __('Log Out') }}
            </button>
        </form>
    </div>
</x-guest-layout>
