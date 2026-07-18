<x-guest-layout>
    <div class="flex flex-col gap-6" x-data="{ showOtp: {{ $errors->has('otp') ? 'true' : 'false' }} }">
        <p class="text-sm text-gray-600">
            {{ __('Waiting for verification... Use the link in the email we sent you.') }}
        </p>

        @if (session('status') == 'verification-link-sent')
            <p class="text-sm font-medium text-green-600">
                {{ __('A new verification link has been sent to the email address you provided during registration.') }}
            </p>
        @endif

        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <x-primary-button>
                {{ __('Resend Verification Email') }}
            </x-primary-button>
        </form>

        <div>
            <button
                type="button"
                class="rounded-md text-sm font-medium text-indigo-600 underline hover:text-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                x-on:click="showOtp = true"
                x-bind:aria-expanded="showOtp"
                aria-controls="otp-verification-form"
            >
                {{ __('Use an OTP code') }}
            </button>
        </div>

        <template x-if="showOtp">
            <form
                id="otp-verification-form"
                method="POST"
                action="{{ route('verification.verify-otp') }}"
            >
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
        </template>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                {{ __('Log Out') }}
            </button>
        </form>
    </div>
</x-guest-layout>
