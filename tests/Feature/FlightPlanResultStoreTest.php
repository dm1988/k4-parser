<?php

namespace Tests\Feature;

use App\Models\FlightPlanResult;
use App\Models\User;
use App\Services\Infrastructure\FlightPlanResultStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FlightPlanResultStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_an_encrypted_result_with_an_opaque_key(): void
    {
        $store = app(FlightPlanResultStore::class);
        $owner = User::factory()->create();
        $flightPlan = ['flight_plan_data' => ['route' => ['departure' => 'PANC']]];

        $resultKey = $store->save($owner, $flightPlan);
        $rawPayload = DB::table((new FlightPlanResult)->getTable())->value('payload');

        $this->assertTrue(Str::isUlid($resultKey));
        $this->assertIsString($rawPayload);
        $this->assertStringNotContainsString('PANC', $rawPayload);
        $this->assertSame($flightPlan, $store->get($owner, $resultKey));
    }

    public function test_saving_a_new_result_atomically_replaces_the_users_current_result(): void
    {
        $store = app(FlightPlanResultStore::class);
        $owner = User::factory()->create();
        $oldResultKey = $store->save($owner, ['flight_plan_data' => ['revision' => 1]]);

        $newResultKey = $store->save($owner, ['flight_plan_data' => ['revision' => 2]]);

        $this->assertNotSame($oldResultKey, $newResultKey);
        $this->assertSame(1, FlightPlanResult::query()->count());
        $this->assertNull($store->get($owner, $oldResultKey));
        $this->assertSame(
            ['flight_plan_data' => ['revision' => 2]],
            $store->get($owner, $newResultKey),
        );
    }

    public function test_lookup_rejects_malformed_and_foreign_result_keys(): void
    {
        $store = app(FlightPlanResultStore::class);
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $resultKey = $store->save($owner, ['flight_plan_data' => []]);

        $this->assertNull($store->get($owner, 'not-a-valid-result-key'));
        $this->assertNull($store->get($otherUser, $resultKey));
        $this->assertSame(['flight_plan_data' => []], $store->get($owner, $resultKey));
    }

    public function test_latest_returns_only_the_owners_current_result(): void
    {
        $store = app(FlightPlanResultStore::class);
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $resultKey = $store->save($owner, ['flight_plan_data' => []]);
        $store->save($otherUser, ['flight_plan_data' => ['other' => true]]);

        $latest = $store->latest($owner);

        $this->assertInstanceOf(FlightPlanResult::class, $latest);
        $this->assertSame($resultKey, $latest->result_key);
        $this->assertTrue($latest->user->is($owner));
    }

    public function test_delete_is_scoped_to_the_owner_and_result_key(): void
    {
        $store = app(FlightPlanResultStore::class);
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $resultKey = $store->save($owner, ['flight_plan_data' => []]);

        $store->delete($otherUser, $resultKey);
        $store->delete($owner, 'not-a-valid-result-key');

        $this->assertSame(['flight_plan_data' => []], $store->get($owner, $resultKey));

        $store->delete($owner, $resultKey);

        $this->assertNull($store->get($owner, $resultKey));
    }

    public function test_deleting_a_user_cascades_to_the_saved_result(): void
    {
        $user = User::factory()->create();
        $result = FlightPlanResult::factory()->for($user)->create();

        $user->delete();

        $this->assertModelMissing($result);
    }
}
