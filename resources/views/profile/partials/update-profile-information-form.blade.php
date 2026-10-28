<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="mt-2 text-sm text-gray-800 dark:text-gray-200">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification" class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:text-gray-400 dark:hover:text-white dark:focus:ring-offset-gray-900">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 text-sm font-medium text-green-600 dark:text-green-400">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <fieldset id="user-preferences" class="space-y-6 border-t border-gray-200 pt-6 dark:border-gray-700">
            <legend class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ __('User preferences') }}</legend>
            <fieldset class="space-y-4">
                <legend class="mb-4 text-base font-semibold text-[#1B365D] dark:text-amber-200">{{ __('Schedule Extractor') }}</legend>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="airline_iata_code" :value="__('Airline IATA code')" />
                        <x-text-input id="airline_iata_code" name="airline_iata_code" type="text" class="mt-1 block w-full uppercase" :value="old('airline_iata_code', $user->airline_iata_code)" maxlength="2" placeholder="{{ config('schedule.airline_codes.iata') }}" autocomplete="off" />
                        <x-input-error class="mt-2" :messages="$errors->get('airline_iata_code')" />
                    </div>

                    <div>
                        <x-input-label for="airline_icao_code" :value="__('Airline ICAO code')" />
                        <x-text-input id="airline_icao_code" name="airline_icao_code" type="text" class="mt-1 block w-full uppercase" :value="old('airline_icao_code', $user->airline_icao_code)" maxlength="3" placeholder="{{ config('schedule.airline_codes.icao') }}" autocomplete="off" />
                        <x-input-error class="mt-2" :messages="$errors->get('airline_icao_code')" />
                    </div>

                    <p class="text-sm text-gray-600 dark:text-gray-400 sm:col-span-2">
                        {{ __('Leave these blank to use the configured schedule airline codes.') }}
                    </p>
                </div>

                <div>
                    <x-input-label for="flight_tracker_preference" :value="__('Flight Tracker')" />
                    <select id="flight_tracker_preference" name="flight_tracker_preference" aria-describedby="flight-tracker-help"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:focus:border-indigo-600 dark:focus:ring-indigo-600">
                        @foreach ($flightTrackers as $tracker)
                            <option value="{{ $tracker->value }}" @selected(old('flight_tracker_preference', $selectedFlightTracker) === $tracker->value)>{{ $tracker->label() }}</option>
                        @endforeach
                    </select>
                    <p id="flight-tracker-help" class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        {{ __('Used for schedule flight links and iCal downloads, including previously parsed schedules. Tracks the tail number when available, otherwise the flight number.') }}
                    </p>
                    <x-input-error class="mt-2" :messages="$errors->get('flight_tracker_preference')" />
                </div>
            </fieldset>

            <fieldset id="k4-bids-preferences" class="scroll-mt-6 space-y-4 border-t border-gray-200 pt-6 dark:border-gray-700">
                <legend class="text-base font-semibold text-[#1B365D] dark:text-amber-200">{{ __('K4 Bids') }}</legend>
                <div>
                    <x-input-label for="crew_bid_seat_preference" :value="__('Bid seat')" />
                    <select id="crew_bid_seat_preference" name="crew_bid_seat_preference" aria-describedby="bid-seat-help" required
                        class="mt-1 block min-h-11 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:focus:border-indigo-600 dark:focus:ring-indigo-600">
                        <option value="FO" @selected(old('crew_bid_seat_preference', $user->crew_bid_seat_preference) === 'FO')>{{ __('First Officer') }}</option>
                        <option value="CA" @selected(old('crew_bid_seat_preference', $user->crew_bid_seat_preference) === 'CA')>{{ __('Captain') }}</option>
                    </select>
                    <p id="bid-seat-help" class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ __('Used when opening K4 Bids. Each seat keeps its own preferences and bid list.') }}</p>
                    <x-input-error class="mt-2" :messages="$errors->get('crew_bid_seat_preference')" />
                </div>
            </fieldset>
        </fieldset>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-600 dark:text-gray-400"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
