<?php

namespace Tests\Feature;

use App\Livewire\FlightPlanBrief;
use App\Models\FlightDelay;
use App\Models\FlightPlanResult;
use App\Models\User;
use App\Services\Infrastructure\FlightPlanResultStore;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FlightDelayHistoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_history_link_depends_on_the_owners_reports_with_and_without_a_brief(): void
    {
        $user = User::factory()->admin()->create();
        FlightDelay::factory()->partial()->create();

        Livewire::actingAs($user)->test(FlightPlanBrief::class)->assertDontSeeText('Delay history');
        $delay = FlightDelay::factory()->for($user)->partial()->create();
        Livewire::actingAs($user)->test(FlightPlanBrief::class)->assertSeeText('Delay history');

        FlightPlanResult::factory()->for($user)->create(['payload' => [
            'flight_plan_data' => ['identity' => [], 'schedule' => [], 'route' => ['departure' => 'PANC', 'destination' => 'KMIA']],
        ]]);
        Livewire::actingAs($user)->test(FlightPlanBrief::class)->assertSeeText('Delay history');
        $delay->delete();
        Livewire::actingAs($user)->test(FlightPlanBrief::class)->assertDontSeeText('Delay history');
    }

    public function test_empty_history_is_available_without_a_loaded_flight_plan(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('flight-delays.index'))
            ->assertOk()->assertSeeText('No delay reports recorded yet.');
    }

    public function test_history_survives_replacement_and_clearing_without_changing_the_active_brief(): void
    {
        $user = User::factory()->admin()->create();
        $store = app(FlightPlanResultStore::class);
        $oldKey = $store->save($user, ['flight_plan_data' => []]);
        $delay = FlightDelay::factory()->for($user)->completed()->create(['flight_plan_key' => $oldKey]);
        $newKey = $store->save($user, ['flight_plan_data' => ['identity' => ['flightNumber' => 'CKS999']]]);

        $this->actingAs($user)->get(route('flight-delays.show', $delay))
            ->assertOk()->assertSeeText('CKS241')->assertSeeText('Copy report text')
            ->assertDontSeeHtml('wire:model');
        $this->assertSame($newKey, $store->latest($user)->result_key);
        $store->delete($user, $newKey);
        $this->get(route('flight-delays.show', $delay))->assertOk()->assertSeeText('PANC');
        $this->assertModelExists($delay);
    }

    public function test_another_users_reports_are_absent_from_history_and_detail(): void
    {
        $user = User::factory()->admin()->create();
        $other = FlightDelay::factory()->completed()->create(['flight_context' => ['flight_number' => 'PRIVATE999']]);
        FlightDelay::factory()->for($user)->partial()->create();

        $this->actingAs($user)->get(route('flight-delays.index'))->assertOk()
            ->assertSeeText('CKS241')->assertDontSeeText('PRIVATE999');
        $this->get(route('flight-delays.show', $other))->assertNotFound();
    }

    public function test_history_orders_equal_timestamps_by_id_and_paginates(): void
    {
        $user = User::factory()->admin()->create();
        FlightDelay::factory()->for($user)->count(16)->create(['updated_at' => '2026-10-26 12:00:00']);
        $ids = $user->flightDelays()->orderByDesc('id')->pluck('id')->all();

        $this->actingAs($user)->get(route('flight-delays.index'))->assertOk()
            ->assertViewHas('delays', fn ($delays): bool => $delays->modelKeys() === array_slice($ids, 0, 15));
        $this->get(route('flight-delays.index', ['page' => 2]))->assertOk()
            ->assertViewHas('delays', fn ($delays): bool => $delays->modelKeys() === array_slice($ids, 15));
    }

    public function test_report_text_has_an_ordered_timeline_declared_duration_and_multiline_notes(): void
    {
        $user = User::factory()->admin()->create();
        $delay = FlightDelay::factory()->for($user)->completed()->create([
            'fueling_completed_at' => '2026-10-25 23:45:00',
            'notes' => "First line\nSecond line",
        ]);
        $expected = "Flight delay report\nAll times UTC\nFlight: CKS241\nFlight date: 2026-10-25\nTail: N774CK\nDeparture: PANC\nDestination: KMIA\nScheduled departure (UTC): 2026-10-25 23:30z\n\nRecorded event timeline:\n2026-10-25 23:45z — Logbook received\n2026-10-25 23:45z — Fueling completed\n2026-10-26 00:15z — New payload received\n\nDelay started: 2026-10-25 23:30z\nDelay ended: 2026-10-26 00:15z\nDelay duration: 45 min (entered delay start/end interval)\n\nDelay cause:\nAwaiting revised payload.\n\nActions taken:\nCoordinated with flight following.\n\nNotes:\nFirst line\nSecond line";

        $this->actingAs($user)->get(route('flight-delays.show', $delay))->assertOk()->assertViewHas('reportText', $expected);
    }

    #[DataProvider('scheduledDepartures')]
    public function test_saved_reports_format_departure_and_saved_times_in_utc(?string $departure, string $expected): void
    {
        $user = User::factory()->admin()->create();
        $delay = FlightDelay::factory()->for($user)->create([
            'flight_context' => ['scheduled_departure_utc' => $departure],
            'updated_at' => '2026-10-26 03:47:59',
        ]);

        $this->actingAs($user)->get(route('flight-delays.show', $delay))->assertOk()
            ->assertSeeText($expected)
            ->assertSeeText('Last saved 2026-10-26 03:47z')
            ->assertViewHas('flightDetails', fn (array $details): bool => $details['Scheduled departure (UTC)'] === $expected);
        $this->get(route('flight-delays.index'))->assertOk()
            ->assertSeeText('Saved 2026-10-26 03:47z');
        $this->assertSame($departure, $delay->fresh()->flight_context['scheduled_departure_utc']);
    }

    /** @return array<string, array{?string, string}> */
    public static function scheduledDepartures(): array
    {
        return [
            'UTC' => ['2026-10-26T03:47:59+00:00', '2026-10-26 03:47z'],
            'offset crosses midnight' => ['2026-10-26T01:47:59+09:00', '2026-10-25 16:47z'],
            'missing' => [null, 'Not recorded'],
            'empty' => ['', 'Not recorded'],
            'whitespace' => ['   ', 'Not recorded'],
            'unreadable' => ['invalid timestamp', 'Not recorded'],
        ];
    }

    public function test_partial_reports_handle_missing_identity_and_escape_free_text(): void
    {
        $user = User::factory()->admin()->create();
        $fields = ['other_label', 'other_details', 'notes', 'delay_cause', 'delay_timeline', 'actions_taken', 'additional_facts'];
        $values = array_fill_keys($fields, '</textarea><script>alert(1)</script>');
        $delay = FlightDelay::factory()->for($user)->create([...$values, 'flight_context' => []]);

        $response = $this->actingAs($user)->get(route('flight-delays.show', $delay))->assertOk()
            ->assertSeeHtml('&lt;/textarea&gt;&lt;script&gt;')->assertDontSeeHtml('</textarea><script>alert(1)</script>');
        $text = $response->viewData('reportText');
        $this->assertStringContainsString('Flight: Not recorded', $text);
        $this->assertStringContainsString('Delay duration: Not available', $text);
        $this->assertStringNotContainsString('Subject:', $text);
        $this->assertSame(7, substr_count($text, '</textarea><script>alert(1)</script>'));
    }

    public function test_history_requires_authentication_verification_feature_and_gate_access(): void
    {
        $delay = FlightDelay::factory()->partial()->create();
        $this->get(route('flight-delays.index'))->assertRedirect(route('login'));
        $this->get(route('flight-delays.show', $delay))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->unverified()->create())->get(route('flight-delays.index'))
            ->assertRedirect(route('verification.notice'));
        $this->actingAs(User::factory()->create())->get(route('flight-delays.index'))->assertForbidden();
        Config::set('features.flight_release.enabled', false);
        $this->actingAs(User::factory()->admin()->create())->get(route('flight-delays.index'))->assertNotFound();
    }

    public function test_deleting_an_account_removes_its_reports_only(): void
    {
        $owner = User::factory()->admin()->create();
        $report = FlightDelay::factory()->for($owner)->partial()->create();
        $other = FlightDelay::factory()->partial()->create();

        $owner->delete();

        $this->assertModelMissing($report);
        $this->assertModelExists($other);
    }

    public function test_delay_reporting_has_a_task_route_and_does_not_create_a_record_on_view(): void
    {
        $user = User::factory()->admin()->create();
        FlightPlanResult::factory()->for($user)->create(['payload' => [
            'flight_plan_data' => ['identity' => [], 'schedule' => [], 'route' => ['departure' => 'PANC', 'destination' => 'KMIA']],
        ]]);

        $this->actingAs($user)->get(route('flight-release.task', ['task' => 'delay-reporting']))
            ->assertOk()->assertSeeText('Record a flight delay')->assertSeeText('Delay reporting');
        $this->assertDatabaseCount('flight_delays', 0);
    }
}
