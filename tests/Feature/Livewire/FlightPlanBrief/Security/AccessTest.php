<?php

namespace Tests\Feature\Livewire\FlightPlanBrief\Security;

use App\Enums\FlightPlanTask;
use App\Livewire\FlightPlanBrief;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Feature\Livewire\FlightPlanBrief\FlightPlanBriefTestCase;

class AccessTest extends FlightPlanBriefTestCase
{
    public function test_the_derived_view_state_is_not_serialized_to_the_client(): void
    {
        $component = Livewire::actingAs(User::factory()->admin()->create())
            ->test(FlightPlanBrief::class);

        $this->assertArrayNotHasKey('view', $component->getData());
        $this->assertArrayNotHasKey('isResultsView', $component->getData());
        $this->assertFalse($component->viewData('isResultsView'));
    }

    public function test_the_result_key_cannot_be_changed_by_the_client(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);
        $this->expectExceptionMessage('Cannot update locked property: [flightPlanKey]');

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(FlightPlanBrief::class)
            ->set('flightPlanKey', '01JTESTRESULTKEYABC1234567');
    }

    public function test_the_active_task_cannot_be_changed_directly_by_the_client(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);
        $this->expectExceptionMessage('Cannot update locked property: [activeTask]');

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(FlightPlanBrief::class)
            ->set('activeTask', FlightPlanTask::Fms->value);
    }

    public function test_component_actions_enforce_authentication_verification_feature_and_gate_access(): void
    {
        Livewire::test(FlightPlanBrief::class)
            ->call('extractFlightPlan')
            ->assertUnauthorized();

        Livewire::actingAs(User::factory()->unverified()->create())
            ->test(FlightPlanBrief::class)
            ->call('extractFlightPlan')
            ->assertForbidden();

        Config::set('features.flight_release.enabled', false);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(FlightPlanBrief::class)
            ->call('extractFlightPlan')
            ->assertNotFound();

        Config::set('features.flight_release.enabled', true);
        Config::set('features.flight_release.for_all_users', false);

        Livewire::actingAs(User::factory()->create())
            ->test(FlightPlanBrief::class)
            ->call('extractAnotherFlightPlan')
            ->assertForbidden();
    }
}
