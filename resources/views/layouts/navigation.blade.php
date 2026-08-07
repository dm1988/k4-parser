<nav x-data="{ open: false }" class="border-b border-gray-100 bg-white dark:border-gray-800 dark:bg-gray-900">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex min-h-16 justify-between gap-4">
            <div class="flex min-w-0">
                <!-- Navigation Links -->
                <div class="hidden items-stretch gap-1 sm:flex">
                    @if (Auth::user()?->canUseScheduleExtractor())
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        {{ __('Extract Schedule') }}
                    </x-nav-link>
                    @endif
                    @if (Auth::user()?->canUseFlightRelease())
                    <x-nav-link :href="route('flight-release.index')" :active="request()->routeIs('flight-release.*')">
                        {{ __('Extract Flight Plan') }}
                    </x-nav-link>
                    @endif
                    @if (Auth::user()->canAccessPanel(filament()->getPanel('admin')))
                    <x-nav-link :href="route('filament.admin.pages.dashboard')"
                        :active="request()->routeIs('filament.admin.*')">
                        {{ __('Admin Panel') }}
                    </x-nav-link>
                    @endif
                    <a href="https://buymeacoffee.com/crewcompass"
                        class="inline-flex items-center self-center gap-2 rounded-md border border-amber-600/40 bg-amber-50 px-4 py-2.5 text-sm font-semibold text-amber-800 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:bg-amber-100 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 active:translate-y-0 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300 dark:hover:bg-amber-500/20 dark:focus:ring-amber-400 dark:focus:ring-offset-gray-900"
                        target="_blank" rel="noopener noreferrer">
                        <span class="text-base" aria-hidden="true">☕</span>
                        <span>Buy me a coffee</span>
                    </a>
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden gap-3 sm:ms-6 sm:flex sm:items-center">
                <x-theme-selector id="desktop-theme-selector" />

                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button
                            class="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out hover:text-gray-700 focus:outline-none dark:bg-gray-900 dark:text-gray-300 dark:hover:text-white">
                            <div>{{ Auth::user()->name }}</div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                        clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <button type="submit"
                                class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-100 focus:bg-gray-100 focus:outline-none dark:text-gray-300 dark:hover:bg-gray-800 dark:focus:bg-gray-800">
                                {{ __('Log Out') }}
                            </button>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open"
                    aria-controls="mobile-navigation"
                    x-bind:aria-expanded="open"
                    class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none dark:hover:bg-gray-800 dark:hover:text-gray-200 dark:focus:bg-gray-800 dark:focus:text-gray-200">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex"
                            stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round"
                            stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div id="mobile-navigation" :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            @if (Auth::user()?->canUseScheduleExtractor())
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                {{ __('Extract Schedule') }}
            </x-responsive-nav-link>
            @endif
            @if (Auth::user()?->canUseFlightRelease())
            <x-responsive-nav-link :href="route('flight-release.index')"
                :active="request()->routeIs('flight-release.*')">
                {{ __('Extract Flight Plan') }}
            </x-responsive-nav-link>
            @endif
            @if (Auth::user()->canAccessPanel(filament()->getPanel('admin')))
            <x-responsive-nav-link :href="route('filament.admin.pages.dashboard')"
                :active="request()->routeIs('filament.admin.*')">
                {{ __('Admin Panel') }}
            </x-responsive-nav-link>
            @endif
            <x-responsive-nav-link href="https://buymeacoffee.com/crewcompass" :active="false" target="_blank"
                rel="noopener">
                Buy Me a Coffee
            </x-responsive-nav-link>
        </div>

        <!-- Responsive Settings Options -->
        <div class="border-t border-gray-200 pb-1 pt-4 dark:border-gray-700">
            <div class="px-4">
                <div class="text-base font-medium text-gray-800 dark:text-gray-100">{{ Auth::user()->name }}</div>
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <div class="px-4 py-2">
                    <x-theme-selector id="mobile-theme-selector" />
                </div>

                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <button type="submit"
                        class="block w-full border-l-4 border-transparent py-2 pe-4 ps-3 text-start text-base font-medium text-gray-600 transition duration-150 ease-in-out hover:border-gray-300 hover:bg-gray-50 hover:text-gray-800 focus:border-gray-300 focus:bg-gray-50 focus:text-gray-800 focus:outline-none dark:text-gray-300 dark:hover:border-gray-600 dark:hover:bg-gray-800 dark:hover:text-white dark:focus:border-gray-600 dark:focus:bg-gray-800 dark:focus:text-white">
                        {{ __('Log Out') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</nav>
