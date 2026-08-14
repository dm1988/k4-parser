<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-gray-700 focus:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 active:bg-gray-900 dark:bg-indigo-500 dark:hover:bg-indigo-400 dark:focus:bg-indigo-400 dark:focus:ring-indigo-400 dark:focus:ring-offset-gray-900 dark:active:bg-indigo-600']) }}>
    {{ $slot }}
</button>
