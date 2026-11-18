<?php

namespace Tests\Feature;

use App\Exceptions\BidPortalException;
use App\Jobs\RefreshCrewBidSnapshot;
use App\Models\BidPackage;
use App\Models\CrewBidCredentialVault;
use App\Models\User;
use App\Services\CrewBids\CrewBidOperationGuard;
use App\Services\CrewBids\CrewBidSecretStore;
use App\Services\CrewBids\CrewBidVaultCrypto;
use App\Services\CrewBids\CrewBidVaultService;
use App\Services\CrewBids\QueuedCrewBidRefresh;
use App\Services\CrewBids\SyncCrewBidSnapshot;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fixtures\InMemoryCrewBidSecretStore;
use Tests\Fixtures\SharedBidPortal;
use Tests\TestCase;

class CrewBidVaultTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['crew_bids.vault.enabled' => true]);
    }

    public function test_connect_verifies_the_account_and_persists_only_password_encrypted_ciphertext(): void
    {
        $store = $this->memory();
        $http = SharedBidPortal::fake();
        $user = User::factory()->crewBidsAccess()->create();

        $this->actingAs($user)->post(route('crew-bids.connection.store'), $this->input())->assertRedirect(route('crew-bids.connection.index'))
            ->assertSessionMissing('crew_bid_sync_error')->assertSessionHasNoErrors()->assertHeader('Cache-Control', 'no-store, private');

        $vault = CrewBidCredentialVault::query()->sole();
        $raw = DB::table('crew_bid_credential_vaults')->sole()->envelope;
        $this->assertStringNotContainsString('synthetic-password', $raw);
        $this->assertStringNotContainsString('900000', $raw);
        $this->assertArrayNotHasKey('envelope', $vault->toArray());
        $session = json_encode(session()->all(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('synthetic-password', $session);
        $this->assertStringNotContainsString('900000', $session);
        $lease = $store->get('lease:'.session('crew_bid_vault_handle'));
        $this->assertNotNull($lease);
        $this->assertStringNotContainsString($lease['key'], $session);
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->get(route('crew-bids.connection.index'))->assertOk()->assertSee('Unlocked for this session')->assertDontSee('synthetic-password');
        $http->assertNotSent(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), 'BidSheet.aspx'));
    }

    public function test_connection_routes_require_authentication_access_and_an_enabled_feature(): void
    {
        $http = SharedBidPortal::fake();
        $this->get(route('crew-bids.connection.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->post(route('crew-bids.connection.store'), $this->input())->assertForbidden();
        $this->actingAs(User::factory()->crewBidsAccess()->create());
        config(['crew_bids.vault.enabled' => false]);
        $this->get(route('crew-bids.connection.index'))->assertNotFound()->assertHeader('Cache-Control', 'no-store, private');
        $this->post(route('crew-bids.connection.store'), $this->input())->assertNotFound();

        $this->assertDatabaseCount('crew_bid_credential_vaults', 0);
        $http->assertNothingSent();
    }

    public function test_invalid_connection_input_is_redacted_even_on_validation_errors(): void
    {
        $http = SharedBidPortal::fake();
        $user = User::factory()->crewBidsAccess()->create();
        $this->actingAs($user)->from(route('crew-bids.connection.index'))->post(route('crew-bids.connection.store'), [
            'employee' => 'invalid', 'portal_password' => 'synthetic-password', 'app_password' => 'wrong-app-password',
        ])->assertSessionHasErrors(['employee', 'app_password', 'confirmed'])->assertHeader('Cache-Control', 'no-store, private');

        $old = session()->getOldInput();
        $this->assertArrayNotHasKey('employee', $old);
        $this->assertArrayNotHasKey('app_password', $old);
        $this->assertArrayNotHasKey('portal_password', $old);
        $this->assertDatabaseCount('crew_bid_credential_vaults', 0);
        $http->assertNothingSent();
    }

    public function test_insecure_nonlocal_requests_are_rejected_with_debugging_and_caching_disabled(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        config(['app.debug' => true, 'debugbar.enabled' => true]);

        $this->post('http://localhost/crew-bids/connection', $this->input())->assertForbidden()->assertHeader('Cache-Control', 'no-store, private')
            ->assertDontSee('synthetic-password')->assertDontSee('900000');

        $this->assertFalse(config('debugbar.enabled'));
        $this->assertFalse(config('app.debug'));
        $this->assertNull(request()->input('portal_password'));
        $this->assertNull(request()->input('app_password'));
    }

    public function test_failed_portal_verification_preserves_the_previous_connection(): void
    {
        $this->memory();
        $user = User::factory()->crewBidsAccess()->create();
        $vault = CrewBidCredentialVault::factory()->create(['user_id' => $user->id]);
        $before = $vault->fresh()->getRawOriginal();
        $http = SharedBidPortal::fake('rejected-login');

        $this->actingAs($user)->post(route('crew-bids.connection.store'), $this->input())->assertRedirect()->assertSessionHas('crew_bid_sync_error');

        $this->assertSame($before, $vault->fresh()->getRawOriginal());
        $this->assertDatabaseCount('crew_bid_credential_vaults', 1);
        $http->assertSentCount(2);
    }

    public function test_an_unavailable_memory_store_blocks_enrollment_without_a_durable_fallback(): void
    {
        $store = $this->memory();
        $store->unavailable = true;
        $http = SharedBidPortal::fake();
        $this->actingAs(User::factory()->crewBidsAccess()->create())->post(route('crew-bids.connection.store'), $this->input())
            ->assertRedirect()->assertSessionHas('crew_bid_sync_error', 'The secure K4 memory store is unavailable. Try again after service is restored.');

        $this->assertDatabaseCount('crew_bid_credential_vaults', 0);
        $this->assertSame([], $store->entries);
        $http->assertNothingSent();
    }

    public function test_saved_login_can_queue_a_refresh_without_typing_or_persisting_the_portal_password(): void
    {
        $store = $this->memory();
        Queue::fake([RefreshCrewBidSnapshot::class]);
        $user = $this->unlocked();
        $package = BidPackage::factory()->create(['period' => '2026-12']);
        $http = SharedBidPortal::fake();

        $this->post(route('crew-bids.employees.read', $package), ['use_saved_credentials' => 1, 'seat' => 'FO'])->assertRedirect()->assertSessionMissing('crew_bid_sync_error');

        Queue::assertPushed(RefreshCrewBidSnapshot::class, 1);
        $job = Queue::pushed(RefreshCrewBidSnapshot::class)->sole();
        $this->assertNull(Cache::get('crew-bid-refresh:'.$job->refreshId.':credentials'));
        $this->assertSame('synthetic-password', $store->get('refresh:'.$job->refreshId)['password']);
        $http->assertNothingSent();
        $job->handle(app(QueuedCrewBidRefresh::class), app(SyncCrewBidSnapshot::class));
        $this->assertSame('completed', app(QueuedCrewBidRefresh::class)->current($user, $package, 'FO')['state']);
        $this->assertNull($store->get('refresh:'.$job->refreshId));
        $http->assertSentCount(8);
    }

    public function test_lock_cancels_already_queued_saved_and_one_off_logins(): void
    {
        $store = $this->memory();
        Queue::fake([RefreshCrewBidSnapshot::class]);
        $user = $this->unlocked();
        $savedPackage = BidPackage::factory()->create();
        $manualPackage = BidPackage::factory()->create(['period' => '2027-01']);
        $http = SharedBidPortal::fake();
        $this->post(route('crew-bids.employees.read', $savedPackage), ['use_saved_credentials' => 1, 'seat' => 'FO'])->assertRedirect();
        $this->post(route('crew-bids.employees.read', $manualPackage), ['employee' => '900000', 'portal_password' => 'synthetic-password', 'seat' => 'FO'])->assertRedirect();

        $this->delete(route('crew-bids.connection.lock'))->assertRedirect()->assertSessionMissing('crew_bid_vault_handle');

        Queue::assertPushed(RefreshCrewBidSnapshot::class, 2);
        foreach (Queue::pushed(RefreshCrewBidSnapshot::class) as $job) {
            $job->handle(app(QueuedCrewBidRefresh::class), app(SyncCrewBidSnapshot::class));
            $this->assertNull($store->get('refresh:'.$job->refreshId));
        }
        $this->assertSame('failed', app(QueuedCrewBidRefresh::class)->current($user, $savedPackage, 'FO')['state']);
        $this->assertSame('failed', app(QueuedCrewBidRefresh::class)->current($user, $manualPackage, 'FO')['state']);
        $http->assertNothingSent();
    }

    public function test_logging_out_revokes_a_previously_queued_login(): void
    {
        $this->memory();
        Queue::fake([RefreshCrewBidSnapshot::class]);
        $user = $this->unlocked();
        $package = BidPackage::factory()->create();
        $http = SharedBidPortal::fake();
        $this->post(route('crew-bids.employees.read', $package), ['use_saved_credentials' => 1, 'seat' => 'FO'])->assertRedirect();
        $this->post(route('logout'))->assertRedirect('/');

        Queue::assertPushed(RefreshCrewBidSnapshot::class, 1);
        $job = Queue::pushed(RefreshCrewBidSnapshot::class)->sole();
        $job->handle(app(QueuedCrewBidRefresh::class), app(SyncCrewBidSnapshot::class));

        $this->assertSame('failed', app(QueuedCrewBidRefresh::class)->current($user, $package, 'FO')['state']);
        $this->assertDatabaseCount('crew_bid_credential_vaults', 1);
        $http->assertNothingSent();
    }

    public function test_logout_from_another_authentication_entry_point_also_revokes_the_unlock(): void
    {
        $store = $this->memory();
        $this->unlocked();
        $handle = session('crew_bid_vault_handle');

        Auth::guard('web')->logout();

        $this->assertNull(session('crew_bid_vault_handle'));
        $this->assertNull($store->get('lease:'.$handle));
        $this->assertDatabaseCount('crew_bid_credential_vaults', 1);
    }

    public function test_status_navigation_does_not_extend_the_idle_unlock_deadline(): void
    {
        $this->freezeTime();
        $store = $this->memory();
        $this->unlocked();
        $key = 'lease:'.session('crew_bid_vault_handle');
        $before = $store->get($key);
        $this->travel(899)->seconds();
        $this->get(route('crew-bids.connection.index'))->assertOk()->assertSee('Unlocked for this session');
        $this->assertSame($before, $store->get($key));
        $this->travel(2)->seconds();

        $this->get(route('crew-bids.connection.index'))->assertOk()->assertSee('Locked')->assertDontSee('Unlocked for this session');
    }

    public function test_deliberate_activity_renews_idle_time_but_cannot_extend_the_absolute_deadline(): void
    {
        $this->freezeTime();
        $this->memory();
        $user = $this->unlocked();
        for ($i = 0; $i < 8; $i++) {
            $this->travel(840)->seconds();
            $this->assertSame('900000', app(CrewBidVaultService::class)->credentials($user, session()->driver())['employee']);
        }
        $this->travel(481)->seconds();

        $this->expectException(BidPortalException::class);
        app(CrewBidVaultService::class)->credentials($user, session()->driver());
    }

    public function test_another_user_cannot_reuse_a_stolen_opaque_unlock_handle(): void
    {
        $this->memory();
        $this->unlocked();
        $other = User::factory()->crewBidsAccess()->create();
        CrewBidCredentialVault::factory()->create(['user_id' => $other->id]);

        $this->expectException(BidPortalException::class);
        app(CrewBidVaultService::class)->credentials($other, session()->driver());
    }

    public function test_an_unlock_handle_cannot_be_used_by_another_session_for_the_same_owner(): void
    {
        $this->memory();
        $user = $this->unlocked();

        $this->expectException(BidPortalException::class);
        app(CrewBidVaultService::class)->lease($user, 'another-session', session('crew_bid_vault_handle'));
    }

    public function test_enrollment_rejects_a_mismatch_with_the_verified_employee_before_portal_access(): void
    {
        $this->memory();
        $user = User::factory()->crewBidsAccess()->create();
        $user->forceFill(['crew_bid_employee_number' => '910001', 'crew_bid_employee_verified_at' => now()])->save();
        $http = SharedBidPortal::fake();

        $this->actingAs($user)->post(route('crew-bids.connection.store'), $this->input())->assertRedirect()
            ->assertSessionHas('crew_bid_sync_error', 'Use the K4 account matching your verified employee number.');

        $this->assertDatabaseCount('crew_bid_credential_vaults', 0);
        $http->assertNothingSent();
    }

    public function test_wrong_app_password_cannot_create_an_unlock_lease_or_contact_the_portal(): void
    {
        $store = $this->memory();
        $user = User::factory()->crewBidsAccess()->create();
        CrewBidCredentialVault::factory()->create(['user_id' => $user->id]);
        $http = SharedBidPortal::fake();

        $this->actingAs($user)->post(route('crew-bids.connection.unlock'), ['app_password' => 'incorrect'])
            ->assertSessionHasErrors('app_password')->assertSessionMissing('crew_bid_vault_handle');

        $this->assertSame([], $store->entries);
        $http->assertNothingSent();
    }

    public function test_replayed_session_state_cannot_restore_an_operation_after_lock(): void
    {
        $this->memory();
        $user = $this->unlocked();
        $session = session()->driver();
        $guard = app(CrewBidOperationGuard::class);
        $binding = $guard->begin($user, $session);
        $stale = $session->all();
        app(CrewBidVaultService::class)->lock($session);
        $session->replace($stale);
        $session->save();
        $guard->activate($binding);

        $this->expectException(BidPortalException::class);
        $guard->assertAuthorized();
    }

    public function test_lock_during_a_memory_store_outage_revokes_all_owner_grants_via_the_access_epoch(): void
    {
        $store = $this->memory();
        $user = $this->unlocked();
        $epoch = $user->crew_bid_credential_epoch;
        $store->unavailable = true;

        $this->delete(route('crew-bids.connection.lock'))->assertRedirect()->assertSessionMissing('crew_bid_vault_handle');

        $this->assertNotSame($epoch, $user->fresh()->crew_bid_credential_epoch);
        $this->assertDatabaseCount('crew_bid_credential_vaults', 1);
    }

    public function test_unlock_attempts_are_throttled_before_expensive_password_key_derivation(): void
    {
        $this->memory();
        $this->actingAs(User::factory()->crewBidsAccess()->create());
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('crew-bids.connection.unlock'), ['app_password' => 'incorrect'])->assertSessionHasErrors('app_password');
        }

        $this->post(route('crew-bids.connection.unlock'), ['app_password' => 'incorrect'])->assertTooManyRequests()->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_password_change_rewraps_the_key_without_reencrypting_the_payload_and_revokes_unlocks(): void
    {
        $this->memory();
        $user = $this->unlocked();
        $vault = CrewBidCredentialVault::query()->sole();
        $before = $vault->envelope;
        $epoch = $user->crew_bid_credential_epoch;

        $this->from('/profile')->put('/password', ['current_password' => 'password', 'password' => 'new-password', 'password_confirmation' => 'new-password'])
            ->assertRedirect('/profile')->assertSessionHasNoErrors()->assertSessionMissing('crew_bid_vault_handle');

        $after = $vault->fresh()->envelope;
        $this->assertSame($before['payload'], $after['payload']);
        $this->assertSame($before['payload_nonce'], $after['payload_nonce']);
        $this->assertSame(2, $after['wrap_version']);
        $this->assertNotSame($before['wrapped_key'], $after['wrapped_key']);
        $this->assertNotSame($epoch, $user->fresh()->crew_bid_credential_epoch);
        $key = app(CrewBidVaultCrypto::class)->unlock($vault->id, $user->id, $after, 'new-password');
        $this->assertSame('900000', app(CrewBidVaultCrypto::class)->credentials($vault->id, $user->id, $after, $key)['employee']);
        sodium_memzero($key);
        $this->expectException(BidPortalException::class);
        app(CrewBidVaultCrypto::class)->unlock($vault->id, $user->id, $after, 'password');
    }

    public function test_corrupted_vault_blocks_password_change_until_explicitly_forgotten(): void
    {
        $this->memory();
        $user = User::factory()->crewBidsAccess()->create();
        $vault = CrewBidCredentialVault::factory()->create(['user_id' => $user->id]);
        $envelope = $vault->envelope;
        $envelope['wrapped_key'] = base64_encode(random_bytes(48));
        $vault->forceFill(['envelope' => $envelope])->save();

        $this->actingAs($user)->from('/profile')->put('/password', ['current_password' => 'password', 'password' => 'new-password', 'password_confirmation' => 'new-password'])
            ->assertRedirect('/profile')->assertSessionHasErrorsIn('updatePassword', 'current_password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
        $this->assertModelExists($vault);
        $this->delete(route('crew-bids.connection.destroy'), ['app_password' => 'password'])->assertRedirect();
        $this->assertModelMissing($vault);
    }

    public function test_password_reset_and_administrative_replacement_remove_the_unrecoverable_vault(): void
    {
        $this->memory();
        $user = User::factory()->crewBidsAccess()->create();
        $vault = CrewBidCredentialVault::factory()->create(['user_id' => $user->id]);

        event(new PasswordReset($user));
        $this->assertModelMissing($vault);
        $replacement = CrewBidCredentialVault::factory()->create(['user_id' => $user->id]);
        $user->update(['password' => Hash::make('admin-replacement')]);
        $this->assertModelMissing($replacement);
    }

    public function test_login_password_rehash_preserves_the_password_encrypted_vault(): void
    {
        $this->memory();
        $user = User::factory()->crewBidsAccess()->create(['password' => Hash::make('password', ['rounds' => 4])]);
        $vault = CrewBidCredentialVault::factory()->create(['user_id' => $user->id]);
        $before = $vault->envelope;
        Hash::driver()->setRounds(6);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/dashboard')->assertSessionHasNoErrors();

        $this->assertSame(6, Hash::info($user->fresh()->password)['options']['cost']);
        $this->assertSame($before, $vault->fresh()->envelope);
        $this->assertFalse(app(CrewBidVaultService::class)->status($user, session()->driver())['unlocked']);
    }

    public function test_password_rehash_from_another_authentication_entry_point_also_preserves_the_vault(): void
    {
        $this->memory();
        $user = User::factory()->admin()->create(['password' => Hash::make('password', ['rounds' => 4])]);
        $vault = CrewBidCredentialVault::factory()->create(['user_id' => $user->id]);
        $before = $vault->envelope;
        Hash::driver()->setRounds(6);

        $this->assertTrue(Auth::guard('web')->attempt(['email' => $user->email, 'password' => 'password']));

        $this->assertSame(6, Hash::info($user->fresh()->password)['options']['cost']);
        $this->assertSame($before, $vault->fresh()->envelope);
        $this->assertNull(app(CrewBidVaultService::class)->loginRehashOwner);
    }

    public function test_verified_employee_change_and_account_deletion_remove_the_vault(): void
    {
        $this->memory();
        $user = User::factory()->crewBidsAccess()->create();
        $vault = CrewBidCredentialVault::factory()->create(['user_id' => $user->id]);
        $user->forceFill(['crew_bid_employee_number' => '910001', 'crew_bid_employee_verified_at' => now()])->save();
        $this->assertModelMissing($vault);
        $replacement = CrewBidCredentialVault::factory()->create(['user_id' => $user->id]);

        $user->delete();

        $this->assertModelMissing($replacement);
    }

    public function test_reverification_of_the_same_employee_preserves_the_vault_but_unlinking_invalidates_it(): void
    {
        $this->memory();
        $user = User::factory()->crewBidsAccess()->create();
        $user->forceFill(['crew_bid_employee_number' => '900000', 'crew_bid_employee_verified_at' => now()])->save();
        $vault = CrewBidCredentialVault::factory()->create(['user_id' => $user->id]);
        $epoch = $user->crew_bid_credential_epoch;

        $user->forceFill(['crew_bid_employee_number' => '900000', 'crew_bid_employee_verified_at' => now()->addSecond()])->save();

        $this->assertModelExists($vault);
        $this->assertSame($epoch, $user->crew_bid_credential_epoch);
        $user->forceFill(['crew_bid_employee_verified_at' => null])->save();
        $this->assertModelMissing($vault);
        $this->assertNotSame($epoch, $user->crew_bid_credential_epoch);
    }

    public function test_a_connection_can_be_forgotten_when_saved_reuse_is_disabled(): void
    {
        $this->memory();
        $user = User::factory()->crewBidsAccess()->create();
        $vault = CrewBidCredentialVault::factory()->create(['user_id' => $user->id]);
        config(['crew_bids.vault.enabled' => false]);

        $this->actingAs($user)->get(route('crew-bids.connection.index'))->assertOk()->assertSee('Forget K4 connection')->assertDontSee('Unlock K4 connection');
        $this->get(route('profile.edit'))->assertOk()->assertSee('Manage saved K4 connection');
        $this->delete(route('crew-bids.connection.destroy'), ['app_password' => 'password'])->assertRedirect();

        $this->assertModelMissing($vault);
    }

    public function test_loss_of_volatile_state_requires_another_unlock_and_never_restores_a_key_from_app_key(): void
    {
        $store = $this->memory();
        $this->unlocked();
        $store->entries = [];
        $package = BidPackage::factory()->create();
        Queue::fake([RefreshCrewBidSnapshot::class]);
        $http = SharedBidPortal::fake();

        $this->post(route('crew-bids.employees.read', $package), ['use_saved_credentials' => 1, 'seat' => 'FO'])->assertRedirect()->assertSessionHas('crew_bid_sync_error');

        Queue::assertNothingPushed();
        $this->assertDatabaseCount('crew_bid_credential_vaults', 1);
        $this->assertSame([], $store->entries);
        $http->assertNothingSent();
    }

    public function test_access_revoked_and_restored_cannot_revive_an_old_unlock(): void
    {
        $this->memory();
        $user = $this->unlocked();
        $user->update(['crew_bids_access' => false]);
        $user->update(['crew_bids_access' => true]);

        $this->expectException(BidPortalException::class);
        app(CrewBidVaultService::class)->credentials($user, session()->driver());
    }

    #[DataProvider('invalidEnvelopes')]
    public function test_tampered_or_unbounded_envelopes_are_rejected_before_portal_access(string $field, mixed $value): void
    {
        $this->memory();
        $user = User::factory()->crewBidsAccess()->create();
        $vault = CrewBidCredentialVault::factory()->create(['user_id' => $user->id]);
        $envelope = $vault->envelope;
        $envelope[$field] = $value;
        $vault->forceFill(['envelope' => $envelope])->save();
        $http = SharedBidPortal::fake();

        $this->actingAs($user)->post(route('crew-bids.connection.unlock'), ['app_password' => 'password'])->assertRedirect()->assertSessionHas('crew_bid_sync_error')
            ->assertSessionMissing('crew_bid_vault_handle')->assertDontSee('synthetic-password');

        $http->assertNothingSent();
    }

    public static function invalidEnvelopes(): array
    {
        return [
            'unknown format' => ['version', 2], 'unbounded KDF' => ['memory', PHP_INT_MAX], 'invalid salt' => ['salt', 'not-base64'],
            'key tamper' => ['wrapped_key', base64_encode(str_repeat('x', 48))], 'payload tamper' => ['payload', base64_encode(str_repeat('x', 80))],
            'generation tamper' => ['wrap_version', 2],
        ];
    }

    public function test_ciphertext_is_bound_to_its_owner_and_vault_and_independent_of_app_key(): void
    {
        $crypto = app(CrewBidVaultCrypto::class);
        $first = $crypto->seal('one', 1, 'password', '900000', 'synthetic-password');
        $second = $crypto->seal('one', 1, 'password', '900000', 'synthetic-password');
        foreach (['salt', 'wrap_nonce', 'payload_nonce', 'wrapped_key', 'payload'] as $field) {
            $this->assertNotSame($first[$field], $second[$field]);
        }
        config(['app.key' => 'unrelated-key']);
        $key = $crypto->unlock('one', 1, $first, 'password');
        $this->assertSame('900000', $crypto->credentials('one', 1, $first, $key)['employee']);
        sodium_memzero($key);

        $this->expectException(BidPortalException::class);
        $crypto->unlock('two', 2, $first, 'password');
    }

    private function memory(): InMemoryCrewBidSecretStore
    {
        $store = new InMemoryCrewBidSecretStore;
        $this->app->instance(CrewBidSecretStore::class, $store);

        return $store;
    }

    private function unlocked(): User
    {
        $user = User::factory()->crewBidsAccess()->create();
        CrewBidCredentialVault::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user)->post(route('crew-bids.connection.unlock'), ['app_password' => 'password'])->assertRedirect()->assertSessionMissing('crew_bid_sync_error')->assertSessionHasNoErrors();
        $this->withCookie(config('session.cookie'), session()->getId());

        return $user;
    }

    private function input(): array
    {
        return ['app_password' => 'password', 'employee' => '900000', 'portal_password' => 'synthetic-password', 'confirmed' => 1];
    }
}
