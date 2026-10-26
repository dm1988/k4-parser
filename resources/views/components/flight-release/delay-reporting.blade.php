@props(['flightPlanKey' => null])

@if ($flightPlanKey !== null)
    <livewire:flight-delay-form :flight-plan-key="$flightPlanKey" :key="'flight-delay-'.$flightPlanKey" />
@else
    <x-flight-release.empty-state title="Load a flight plan" message="A saved flight plan is required to record delay observations." />
@endif
