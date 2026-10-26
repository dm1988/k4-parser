<?php

namespace Tests\Feature;

use App\Actions\FlightPlan\SaveFlightDelay;
use App\Livewire\FlightDelayForm;
use App\Models\FlightDelay;
use App\Models\FlightPlanResult;
use App\Models\User;
use App\Services\Infrastructure\FlightPlanResultStore;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Exceptions;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class FlightDelayFormTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_viewing_the_form_does_not_create_a_report(): void
    {
        $user = User::factory()->admin()->create();
        $brief = $this->brief($user);

        Livewire::actingAs($user)->test(FlightDelayForm::class, ['flightPlanKey' => $brief->result_key])
            ->assertSeeText('Record a flight delay')
            ->assertSeeText('All dates and times are UTC.')
            ->assertSeeHtml('wire:model.live.debounce.500ms="inputs.notes"')
            ->assertSeeHtml('aria-label="Set logbook received to now in UTC"')
            ->assertSeeText('No observations recorded yet');

        $this->assertDatabaseCount('flight_delays', 0);
    }

    public function test_autosaves_restore_clear_and_encrypt_only_the_owners_report(): void
    {
        $user = User::factory()->admin()->create();
        $brief = $this->brief($user);
        $other = FlightDelay::factory()->partial()->create();

        $component = Livewire::actingAs($user)->test(FlightDelayForm::class, ['flightPlanKey' => $brief->result_key])
            ->set('inputs.notes', "First line\nSecond line")
            ->assertHasNoErrors()
            ->set('inputs.delay_cause', 'Late loading')
            ->assertHasNoErrors();

        $delay = $user->flightDelays()->sole();
        $this->assertSame("First line\nSecond line", $delay->notes);
        $this->assertSame('CKS241', $delay->flight_context['flight_number']);
        $this->assertSame($brief->result_key, $delay->flight_plan_key);
        $this->assertNotSame($delay->notes, $delay->getRawOriginal('notes'));
        $this->assertStringNotContainsString('CKS241', $delay->getRawOriginal('flight_context'));
        $this->assertArrayNotHasKey('notes', $delay->toArray());
        $this->assertArrayNotHasKey('flight_context', $delay->toArray());
        $this->assertSame('Awaiting revised payload.', $other->fresh()->delay_cause);

        Livewire::actingAs($user)->test(FlightDelayForm::class, ['flightPlanKey' => $brief->result_key])
            ->assertSet('inputs.notes', "First line\nSecond line")
            ->assertSet('inputs.delay_cause', 'Late loading');

        $component->set('inputs.delay_cause', '')->assertHasNoErrors();
        $this->assertNull($delay->fresh()->delay_cause);
        $this->assertSame(1, $user->flightDelays()->count());
    }

    public function test_invalid_partial_dates_preserve_saved_values_and_do_not_block_valid_notes(): void
    {
        $user = User::factory()->admin()->create();
        $brief = $this->brief($user);
        $component = Livewire::actingAs($user)->test(FlightDelayForm::class, ['flightPlanKey' => $brief->result_key])
            ->set('inputs.logbook_received_at', '2026-10-25T23:50')
            ->assertHasNoErrors()
            ->set('inputs.logbook_received_at', '2026-10-25T23:')
            ->assertHasErrors(['inputs.logbook_received_at'])
            ->assertSeeText('Logbook received must be a complete date and time in UTC.')
            ->set('inputs.notes', 'Fueling in progress')
            ->assertHasErrors(['inputs.logbook_received_at']);

        $delay = $user->flightDelays()->sole();
        $this->assertSame('2026-10-25 23:50', $delay->logbook_received_at->utc()->format('Y-m-d H:i'));
        $this->assertSame('Fueling in progress', $delay->notes);

        $component->call('generateReport')->assertHasErrors(['inputs.logbook_received_at']);
        $this->assertNull($component->viewData('reportText'));
        $component->set('inputs.logbook_received_at', '')->assertHasNoErrors();
        $this->assertNull($delay->fresh()->logbook_received_at);
    }

    public function test_now_and_manual_times_are_saved_in_utc_across_midnight(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-25T23:58:23Z'));
        $user = User::factory()->admin()->create();
        $brief = $this->brief($user);

        Livewire::actingAs($user)->test(FlightDelayForm::class, ['flightPlanKey' => $brief->result_key])
            ->call('setNow', 'logbook_received_at')
            ->assertSet('inputs.logbook_received_at', '2026-10-25T23:58')
            ->set('inputs.new_payload_received_at', '2026-10-26T00:15')
            ->assertHasNoErrors()
            ->call('generateReport')
            ->assertSeeTextInOrder(['2026-10-25 23:58 UTC', '2026-10-26 00:15 UTC']);

        $this->assertDatabaseHas('flight_delays', ['user_id' => $user->id, 'logbook_received_at' => '2026-10-25 23:58:00']);
        Livewire::actingAs($user)->test(FlightDelayForm::class, ['flightPlanKey' => $brief->result_key])
            ->assertSet('inputs.new_payload_received_at', '2026-10-26T00:15');
    }

    public function test_duration_rejects_negative_intervals_and_accepts_zero_without_ordering_milestones(): void
    {
        $user = User::factory()->admin()->create();
        $brief = $this->brief($user);
        $component = Livewire::actingAs($user)->test(FlightDelayForm::class, ['flightPlanKey' => $brief->result_key])
            ->set('inputs.delay_started_at', '2026-10-25T23:50')
            ->set('inputs.delay_ended_at', '2026-10-25T23:40')
            ->assertHasErrors(['inputs.delay_ended_at'])
            ->set('inputs.notes', 'Waiting')
            ->set('inputs.loading_completed_at', '2026-10-25T23:30');

        $delay = $user->flightDelays()->sole();
        $this->assertNull($delay->delay_ended_at);
        $this->assertSame('Waiting', $delay->notes);
        $component->set('inputs.delay_ended_at', '2026-10-25T23:50')
            ->assertHasNoErrors()
            ->call('generateReport');
        $this->assertStringContainsString('Delay duration: 0 min (entered delay start/end interval)', $component->viewData('reportText'));
    }

    public function test_a_conflicting_tab_keeps_input_without_overwriting_the_saved_report(): void
    {
        $user = User::factory()->admin()->create();
        $brief = $this->brief($user);
        $first = Livewire::actingAs($user)->test(FlightDelayForm::class, ['flightPlanKey' => $brief->result_key]);
        $second = Livewire::actingAs($user)->test(FlightDelayForm::class, ['flightPlanKey' => $brief->result_key]);

        $first->set('inputs.notes', 'First tab')->assertHasNoErrors();
        $second->set('inputs.notes', 'Second tab')->assertHasErrors(['save'])
            ->assertSet('inputs.notes', 'Second tab')
            ->assertSeeText('This report changed in another tab.');

        $this->assertSame('First tab', $user->flightDelays()->sole()->notes);
        $this->assertDatabaseCount('flight_delays', 1);
    }

    #[DataProvider('staleBriefActions')]
    public function test_a_replaced_or_cleared_brief_rejects_stale_writes(string $action): void
    {
        $user = User::factory()->admin()->create();
        $brief = $this->brief($user);
        $component = Livewire::actingAs($user)->test(FlightDelayForm::class, ['flightPlanKey' => $brief->result_key]);
        $store = app(FlightPlanResultStore::class);

        if ($action === 'replace') {
            $store->save($user, $brief->payload);
        } else {
            $store->delete($user, $brief->result_key);
        }

        $component->set('inputs.notes', 'Stale edit')->assertHasErrors(['save'])
            ->assertSet('inputs.notes', 'Stale edit')
            ->call('generateReport')->assertHasErrors(['save']);

        $this->assertNull($component->viewData('reportText'));
        $this->assertDatabaseCount('flight_delays', 0);
    }

    /** @return array<string, array{string}> */
    public static function staleBriefActions(): array
    {
        return ['replaced' => ['replace'], 'cleared' => ['clear']];
    }

    public function test_a_failed_save_preserves_input_and_can_be_retried(): void
    {
        Exceptions::fake();
        $user = User::factory()->admin()->create();
        $brief = $this->brief($user);
        $this->mock(SaveFlightDelay::class)->shouldReceive('handle')->once()->andThrow(new RuntimeException('Unavailable'));

        $component = Livewire::actingAs($user)->test(FlightDelayForm::class, ['flightPlanKey' => $brief->result_key])
            ->set('inputs.notes', 'Keep this input')->assertHasErrors(['save'])
            ->assertSet('inputs.notes', 'Keep this input');

        $this->assertDatabaseCount('flight_delays', 0);
        Exceptions::assertReported(RuntimeException::class);
        $this->app->instance(SaveFlightDelay::class, new SaveFlightDelay);
        $component->call('savePending')->assertHasNoErrors();
        $this->assertSame('Keep this input', $user->flightDelays()->sole()->notes);
    }

    public function test_report_generation_uses_latest_notes_and_escapes_markup(): void
    {
        $user = User::factory()->admin()->create();
        $brief = $this->brief($user);
        $notes = "Latest line\n</textarea><script>alert(1)</script>";

        $component = Livewire::actingAs($user)->test(FlightDelayForm::class, ['flightPlanKey' => $brief->result_key])
            ->set('inputs.notes', $notes)->call('generateReport')->assertHasNoErrors()
            ->assertSeeHtml('&lt;/textarea&gt;&lt;script&gt;')
            ->assertDontSeeHtml('</textarea><script>alert(1)</script>');

        $this->assertStringContainsString($notes, $component->viewData('reportText'));
        $this->assertStringContainsString('both delay endpoints are required', $component->viewData('reportText'));
        $this->assertStringNotContainsString('Subject:', $component->viewData('reportText'));
        $component->set('inputs.notes', 'Changed again');
        $this->assertNull($component->viewData('reportText'));
    }

    public function test_foreign_keys_and_unapproved_now_fields_are_rejected(): void
    {
        $user = User::factory()->admin()->create();
        $brief = $this->brief(User::factory()->admin()->create());
        Livewire::actingAs($user)->test(FlightDelayForm::class, ['flightPlanKey' => $brief->result_key])->assertNotFound();

        $own = $this->brief($user);
        Livewire::actingAs($user)->test(FlightDelayForm::class, ['flightPlanKey' => $own->result_key])
            ->call('setNow', 'notes')->assertStatus(422);
        Livewire::actingAs($user)->test(FlightDelayForm::class, ['flightPlanKey' => $own->result_key])
            ->set('inputs.user_id', $brief->user_id)->assertStatus(422);
        $this->assertDatabaseCount('flight_delays', 0);
    }

    #[DataProvider('lockedIdentifiers')]
    public function test_the_client_cannot_change_flight_ownership_or_the_saved_revision(string $property, mixed $value): void
    {
        $user = User::factory()->admin()->create();
        $brief = $this->brief($user);
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($user)->test(FlightDelayForm::class, ['flightPlanKey' => $brief->result_key])->set($property, $value);
    }

    /** @return array<string, array{string, mixed}> */
    public static function lockedIdentifiers(): array
    {
        return [
            'revision' => ['revision', 5],
            'flight key' => ['flightPlanKey', '01JTESTRESULTKEYABC1234567'],
            'saved baseline' => ['savedInputs', ['notes' => 'Forged baseline']],
        ];
    }

    public function test_the_component_requires_authentication_and_verified_email(): void
    {
        $brief = $this->brief(User::factory()->admin()->create());
        Livewire::test(FlightDelayForm::class, ['flightPlanKey' => $brief->result_key])->assertUnauthorized();
        Livewire::actingAs(User::factory()->unverified()->create())
            ->test(FlightDelayForm::class, ['flightPlanKey' => $brief->result_key])->assertForbidden();
        $this->assertDatabaseCount('flight_delays', 0);
    }

    public function test_clearing_an_empty_form_does_not_create_an_empty_history_record(): void
    {
        $user = User::factory()->admin()->create();
        $brief = $this->brief($user);

        Livewire::actingAs($user)->test(FlightDelayForm::class, ['flightPlanKey' => $brief->result_key])
            ->set('inputs.notes', '   ')->call('savePending')->assertHasNoErrors();

        $this->assertDatabaseCount('flight_delays', 0);
    }

    public function test_a_preview_is_removed_when_another_tab_changes_the_report(): void
    {
        $user = User::factory()->admin()->create();
        $brief = $this->brief($user);
        $first = Livewire::actingAs($user)->test(FlightDelayForm::class, ['flightPlanKey' => $brief->result_key])
            ->set('inputs.notes', 'Original notes')->call('generateReport');
        $this->assertNotNull($first->viewData('reportText'));

        Livewire::actingAs($user)->test(FlightDelayForm::class, ['flightPlanKey' => $brief->result_key])
            ->set('inputs.notes', 'Changed by another tab');

        $first->call('$refresh')->assertSet('inputs.notes', 'Original notes');
        $this->assertNull($first->viewData('reportText'));
    }

    #[DataProvider('invalidFieldValues')]
    public function test_invalid_field_values_are_not_persisted(string $field, mixed $value): void
    {
        $user = User::factory()->admin()->create();
        $brief = $this->brief($user);

        Livewire::actingAs($user)->test(FlightDelayForm::class, ['flightPlanKey' => $brief->result_key])
            ->set('inputs.'.$field, $value)->assertHasErrors(['inputs.'.$field]);

        $this->assertDatabaseCount('flight_delays', 0);
    }

    /** @return array<string, array{string, mixed}> */
    public static function invalidFieldValues(): array
    {
        return [
            'oversized notes' => ['notes', str_repeat('a', 10001)],
            'oversized other label' => ['other_label', str_repeat('a', 161)],
            'non-text notes' => ['notes', ['nested' => 'value']],
            'invalid calendar date' => ['other_at', '2026-02-30T12:00'],
        ];
    }

    public function test_component_actions_recheck_feature_and_gate_access(): void
    {
        $user = User::factory()->admin()->create();
        $brief = $this->brief($user);
        $component = Livewire::actingAs($user)->test(FlightDelayForm::class, ['flightPlanKey' => $brief->result_key]);

        Config::set('features.flight_release.enabled', false);
        $component->call('setNow', 'logbook_received_at')->assertNotFound();
        $this->assertDatabaseCount('flight_delays', 0);

        Config::set('features.flight_release.enabled', true);
        $regular = User::factory()->create();
        $own = $this->brief($regular);
        Config::set('features.flight_release.for_all_users', true);
        $denied = Livewire::actingAs($regular)->test(FlightDelayForm::class, ['flightPlanKey' => $own->result_key]);
        Config::set('features.flight_release.for_all_users', false);
        $denied->call('generateReport')->assertForbidden();
        $this->assertDatabaseCount('flight_delays', 0);
    }

    private function brief(User $user): FlightPlanResult
    {
        return FlightPlanResult::factory()->for($user)->create(['payload' => [
            'flight_plan_data' => [
                'identity' => ['flightNumber' => 'CKS241', 'flightDate' => '2026-10-25', 'tailNumber' => 'N774CK'],
                'schedule' => ['etdUtc' => '2026-10-25T23:30:00+00:00'],
                'route' => ['departure' => 'PANC', 'destination' => 'KMIA'],
            ],
        ]]);
    }
}
