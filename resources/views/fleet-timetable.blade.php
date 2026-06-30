<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-[#C5A059]">Operations</p>
                <h1 class="text-2xl font-semibold text-[#0B0E14]">Fleet timetable</h1>
            </div>
            <div class="text-right text-sm text-gray-600">
                <div class="font-semibold text-[#1B365D]">All times UTC</div>
                <div>30-hour operating window · refreshes every minute</div>
            </div>
        </div>
    </x-slot>

    <livewire:fleet-time-board />
</x-app-layout>
